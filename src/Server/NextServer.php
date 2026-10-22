<?php

declare(strict_types=1);

namespace Next\Server;

use Next\Action\ActionDispatcher;
use Next\Cache\CacheStoreInterface;
use Next\Cache\MemoryCacheStore;
use Next\Config\NextConfig;
use Next\Exception\HttpException;
use Next\Exception\NotFoundException;
use Next\Http\NextRequest;
use Next\Http\NextResponse;
use Next\Http\RequestInterface;
use Next\Http\ResponseEmitter;
use Next\Http\ResponseEmitterInterface;
use Next\Http\ResponseInterface;
use Next\Middleware\MiddlewarePipeline;
use Next\Rendering\PageRenderer;
use Next\Rendering\RendererInterface;
use Next\Routing\FileSystemRouter;
use Next\Routing\RouteMatch;
use Next\Routing\RouterInterface;
use Next\Routing\RouteType;
use Next\Support\ClientScript;
use Throwable;

/**
 * NextServer: Core orchestrator for the NEXT.php framework.
 *
 * Designed around SOLID principles:
 * - Single Responsibility: High-level request pipeline orchestration.
 * - Open/Closed: Extensible via pluggable interfaces (Router, Renderer, Cache, Emitter).
 * - Liskov Substitution: Abstractions can be substituted transparently.
 * - Interface Segregation: Fine-grained interfaces.
 * - Dependency Inversion: Depends on abstractions, not concretions.
 */
class NextServer
{
    private string $projectDir;
    private RuntimeEnvironment $environment;
    private RouterInterface $router;
    private RendererInterface $renderer;
    private CacheStoreInterface $cache;
    private ?MiddlewarePipeline $middlewarePipeline;
    private ResponseEmitterInterface $emitter;
    private ActionDispatcher $actionDispatcher;
    private NextConfig $config;

    public function __construct(
        string $projectDir,
        RuntimeEnvironment $environment = RuntimeEnvironment::DEVELOPMENT,
        ?RouterInterface $router = null,
        ?RendererInterface $renderer = null,
        ?CacheStoreInterface $cache = null,
        ?MiddlewarePipeline $middlewarePipeline = null,
        ?ResponseEmitterInterface $emitter = null,
        ?ActionDispatcher $actionDispatcher = null,
        ?NextConfig $config = null
    ) {
        $this->projectDir = rtrim(str_replace('\\', '/', $projectDir), '/');
        $this->environment = $environment;

        $appDir = $this->projectDir . '/app';
        $this->router = $router ?? new FileSystemRouter($appDir);
        $this->renderer = $renderer ?? new PageRenderer();
        $this->cache = $cache ?? new MemoryCacheStore();
        $this->middlewarePipeline = $middlewarePipeline;
        $this->emitter = $emitter ?? new ResponseEmitter();
        $this->actionDispatcher = $actionDispatcher ?? new ActionDispatcher();

        $configFile = $this->projectDir . '/next.config.php';
        $this->config = $config ?? NextConfig::loadFromFile($configFile);

        // Load middleware from file if not explicitly injected
        if ($this->middlewarePipeline === null) {
            $middlewareFile = $this->projectDir . '/middleware.php';
            $this->middlewarePipeline = MiddlewarePipeline::loadFromFile($middlewareFile) ?? new MiddlewarePipeline();
        }
    }

    public function getRouter(): RouterInterface
    {
        return $this->router;
    }

    public function getRenderer(): RendererInterface
    {
        return $this->renderer;
    }

    public function getCache(): CacheStoreInterface
    {
        return $this->cache;
    }

    public function getActionDispatcher(): ActionDispatcher
    {
        return $this->actionDispatcher;
    }

    public function getConfig(): NextConfig
    {
        return $this->config;
    }

    /**
     * Run the server: reads from PHP globals, handles request, and emits response.
     */
    public function run(): void
    {
        $request = NextRequest::fromGlobals();
        $response = $this->handle($request);
        $this->emitter->emit($response);
    }

    /**
     * Handle an incoming HTTP request through the full Next.js lifecycle.
     */
    public function handle(RequestInterface $request): ResponseInterface
    {
        if (!$request instanceof NextRequest) {
            $request = new NextRequest(
                method: $request->getMethod(),
                uri: $request->getUri(),
                headers: $request->getHeaders(),
                body: $request->getBody(),
                cookies: $request->getCookies(),
                queryParams: $request->getQueryParams(),
                serverParams: $request->getServerParams()
            );
        }

        try {
            // 0. Production Health Check Endpoint
            if ($request->getPath() === '/__health') {
                return NextResponse::json([
                    'status' => 'healthy',
                    'framework' => 'NEXT.php',
                    'version' => '1.0.0',
                    'environment' => $this->environment->value,
                    'timestamp' => time(),
                ]);
            }

            // 1. Configured Redirects
            foreach ($this->config->getRedirects() as $redirect) {
                if ($this->pathMatches($request->getPath(), $redirect['source'])) {
                    $status = ($redirect['permanent'] ?? false) ? 308 : 307;
                    return NextResponse::redirect($redirect['destination'], $status);
                }
            }

            // 2. Configured Rewrites
            foreach ($this->config->getRewrites() as $rewrite) {
                if ($this->pathMatches($request->getPath(), $rewrite['source'])) {
                    $request = $request->withPath($rewrite['destination']);
                    break;
                }
            }

            // 3. Static Public Assets
            $publicFileResponse = $this->servePublicAsset($request->getPath(), $request);
            if ($publicFileResponse !== null) {
                return $publicFileResponse;
            }

            // 4. Server Actions Check
            if ($this->actionDispatcher->isActionRequest($request)) {
                return $this->actionDispatcher->dispatch($request);
            }

            // 5. Middleware Pipeline
            $response = $this->middlewarePipeline->handle($request, function (NextRequest $req): NextResponse {
                return $this->dispatchRoute($req);
            });

            // If middleware requested rewrite
            if ($response->isRewrite() && $response->getRewriteUrl() !== null) {
                $rewrittenReq = $request->withPath($response->getRewriteUrl());
                $response = $this->dispatchRoute($rewrittenReq);
            }

            // 6. Apply custom headers from next.config.php
            $response = $this->applyConfigHeaders($request->getPath(), $response);

            return $response;
        } catch (NotFoundException $e) {
            return $this->handleNotFound($request);
        } catch (HttpException $e) {
            return $this->handleHttpException($e, $request);
        } catch (Throwable $e) {
            return $this->handleException($e, $request);
        }
    }

    /**
     * Dispatches the request to either an API route or a Page route.
     */
    private function dispatchRoute(NextRequest $request): NextResponse
    {
        $match = $this->router->match($request);

        if ($match === null) {
            throw new NotFoundException("Route not found for path: {$request->getPath()}");
        }

        if ($match->getType() === RouteType::API) {
            return $this->executeApiRoute($match, $request);
        }

        return $this->executePageRoute($match, $request);
    }

    /**
     * Execute an API Route Handler (route.php).
     */
    private function executeApiRoute(RouteMatch $match, NextRequest $request): NextResponse
    {
        $filePath = $match->getFilePath();
        if (!file_exists($filePath)) {
            throw new NotFoundException("API route handler file not found: {$filePath}");
        }

        $handler = require $filePath;
        $method = $request->getMethod();
        $context = ['params' => $match->getParams()];

        // If returned array of methods: ['GET' => fn(), 'POST' => fn()]
        if (is_array($handler)) {
            if (isset($handler[$method]) && is_callable($handler[$method])) {
                $result = $handler[$method]($request, $context);
                return $this->normalizeResponse($result);
            }

            // Handle OPTIONS automatically if not explicitly provided
            if ($method === 'OPTIONS') {
                $allowed = array_keys(array_filter($handler, 'is_callable'));
                return new NextResponse('', 204, [
                    'Allow' => implode(', ', $allowed),
                ]);
            }

            $allowed = implode(', ', array_keys(array_filter($handler, 'is_callable')));
            return NextResponse::json([
                'error' => "Method {$method} Not Allowed",
            ], 405, ['Allow' => $allowed]);
        }

        // If returned single callable: fn(NextRequest $req, array $ctx)
        if (is_callable($handler)) {
            $result = $handler($request, $context);
            return $this->normalizeResponse($result);
        }

        // If returned class or object with method matching HTTP verb
        if (is_object($handler)) {
            $methodName = strtolower($method);
            if (method_exists($handler, $methodName)) {
                $result = $handler->$methodName($request, $context);
                return $this->normalizeResponse($result);
            }

            return NextResponse::json([
                'error' => "Method {$method} Not Allowed",
            ], 405);
        }

        return NextResponse::json([
            'error' => 'Invalid API route definition',
        ], 500);
    }

    /**
     * Execute a Page Route (page.php wrapped in layout.php chain).
     */
    private function executePageRoute(RouteMatch $match, NextRequest $request): NextResponse
    {
        $cacheKey = 'route:' . $request->getPath();

        // Check Static / ISR Cache (only in production or when caching enabled)
        if ($this->environment === RuntimeEnvironment::PRODUCTION && $this->cache->has($cacheKey)) {
            $cachedHtml = $this->cache->get($cacheKey);
            $isStale = $this->cache->isStale($cacheKey);

            if ($cachedHtml !== null) {
                if (!$isStale) {
                    return NextResponse::html($cachedHtml, 200, ['X-Nextjs-Cache' => 'HIT']);
                }

                // Stale-While-Revalidate: serve stale, revalidate asynchronously or immediately
                $freshHtml = $this->renderPageAndInjectScript($match, $request);
                $this->cache->set($cacheKey, $freshHtml, $this->config->getRevalidateDefault(), ['page']);
                return NextResponse::html($cachedHtml, 200, ['X-Nextjs-Cache' => 'STALE']);
            }
        }

        // Render page
        $html = $this->renderPageAndInjectScript($match, $request);

        // Store into cache if revalidate is configured
        $revalidateTtl = $this->config->getRevalidateDefault();
        if ($this->environment === RuntimeEnvironment::PRODUCTION && $revalidateTtl > 0) {
            $this->cache->set($cacheKey, $html, $revalidateTtl, ['page']);
        }

        return NextResponse::html($html, 200, [
            'X-Nextjs-Cache' => 'MISS',
        ]);
    }

    private function renderPageAndInjectScript(RouteMatch $match, NextRequest $request): string
    {
        $html = $this->renderer->render($match, $request);

        // Inject Swup.js client transition engine (active by default)
        return $this->injectSwupClientScript($html);
    }

    private function injectSwupClientScript(string $html): string
    {
        if (!$this->config->isSwupEnabled()) {
            return $html;
        }

        $clientScript = ClientScript::render($this->config->getSwupConfig());
        if (stripos($html, '</body>') !== false) {
            return (string) preg_replace('/<\/body>/i', $clientScript . "\n</body>", $html, 1);
        }

        return $html . $clientScript;
    }

    private function handleNotFound(RequestInterface $request): NextResponse
    {
        $appDir = $this->projectDir . '/app';
        $notFoundFile = file_exists($appDir . '/not-found.php') ? $appDir . '/not-found.php' : null;
        $layoutFiles = file_exists($appDir . '/layout.php') ? [$appDir . '/layout.php'] : [];

        $html = $this->renderer->renderNotFound($request, $notFoundFile, $layoutFiles);
        $html = $this->injectSwupClientScript($html);
        return NextResponse::html($html, 404);
    }

    private function handleHttpException(HttpException $e, RequestInterface $request): NextResponse
    {
        return NextResponse::json([
            'error' => $e->getMessage(),
            'code' => $e->getStatusCode(),
        ], $e->getStatusCode(), $e->getHeaders());
    }

    private function handleException(Throwable $e, RequestInterface $request): NextResponse
    {
        // Production logging
        error_log(sprintf(
            '[NEXT.php] 500 Server Error: %s in %s:%d (URL: %s)',
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $request->getUri()
        ));

        $appDir = $this->projectDir . '/app';
        $errorFile = file_exists($appDir . '/error.php') ? $appDir . '/error.php' : null;
        $layoutFiles = file_exists($appDir . '/layout.php') ? [$appDir . '/layout.php'] : [];

        $isProduction = ($this->environment === RuntimeEnvironment::PRODUCTION);
        $html = $this->renderer->renderError($e, $request, $errorFile, $layoutFiles, $isProduction);
        $html = $this->injectSwupClientScript($html);
        return NextResponse::html($html, 500);
    }

    private function servePublicAsset(string $path, NextRequest $request): ?NextResponse
    {
        if ($path === '/' || str_contains($path, "\0")) {
            return null;
        }

        $publicDir = $this->projectDir . '/public';
        $realPublicDir = realpath($publicDir);
        if ($realPublicDir === false) {
            return null;
        }

        $filePath = $realPublicDir . $path;
        $realFilePath = realpath($filePath);

        // Security: Prevent Directory Traversal
        if ($realFilePath === false || !str_starts_with($realFilePath, $realPublicDir) || !is_file($realFilePath)) {
            return null;
        }

        // Generate ETag based on mtime and size
        $mtime = filemtime($realFilePath);
        $size = filesize($realFilePath);
        $etag = sprintf('W/"%x-%x"', $mtime, $size);

        // Check 304 Not Modified
        $ifNoneMatch = $request->getHeader('If-None-Match');
        if ($ifNoneMatch !== null && trim($ifNoneMatch) === $etag) {
            return new NextResponse('', 304, [
                'ETag' => $etag,
                'Cache-Control' => 'public, max-age=31536000, immutable',
            ]);
        }

        $extension = strtolower(pathinfo($realFilePath, PATHINFO_EXTENSION));
        $contentTypes = [
            'html' => 'text/html; charset=utf-8',
            'css' => 'text/css; charset=utf-8',
            'js' => 'application/javascript; charset=utf-8',
            'json' => 'application/json; charset=utf-8',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'webp' => 'image/webp',
            'txt' => 'text/plain; charset=utf-8',
            'woff2' => 'font/woff2',
            'woff' => 'font/woff',
        ];

        $mimeType = $contentTypes[$extension] ?? (mime_content_type($realFilePath) ?: 'application/octet-stream');
        $content = file_get_contents($realFilePath);

        return new NextResponse(
            body: $content !== false ? $content : '',
            statusCode: 200,
            headers: [
                'Content-Type' => $mimeType,
                'Content-Length' => (string) $size,
                'ETag' => $etag,
                'Cache-Control' => 'public, max-age=31536000, immutable',
            ]
        );
    }

    private function applyConfigHeaders(string $path, NextResponse $response): NextResponse
    {
        foreach ($this->config->getHeaders() as $rule) {
            if ($this->pathMatches($path, $rule['source'])) {
                foreach ($rule['headers'] as $name => $value) {
                    $response = $response->withHeader($name, $value);
                }
            }
        }
        return $response;
    }

    private function pathMatches(string $path, string $pattern): bool
    {
        $regex = preg_quote($pattern, '#');
        $regex = str_replace(['\\:path\\*', '\\:path\\+', '\\*'], ['.*', '.+', '.*'], $regex);
        return (bool) preg_match('#^' . $regex . '$#', $path);
    }

    private function normalizeResponse(mixed $result): NextResponse
    {
        if ($result instanceof NextResponse) {
            return $result;
        }

        if (is_array($result) || is_object($result)) {
            return NextResponse::json($result);
        }

        return NextResponse::html((string) $result);
    }
}
