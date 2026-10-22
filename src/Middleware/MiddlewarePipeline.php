<?php

declare(strict_types=1);

namespace Next\Middleware;

use Next\Http\NextRequest;
use Next\Http\NextResponse;

/**
 * Middleware pipeline handling sequential request filtering and transformations.
 */
class MiddlewarePipeline
{
    /** @var list<array{middleware: MiddlewareInterface|callable, matchers: list<string>}> */
    private array $middlewares = [];

    /**
     * Add middleware with optional path matcher patterns.
     *
     * @param MiddlewareInterface|callable $middleware
     * @param list<string> $matchers
     */
    public function pipe(MiddlewareInterface|callable $middleware, array $matchers = []): self
    {
        $this->middlewares[] = [
            'middleware' => $middleware,
            'matchers' => $matchers,
        ];
        return $this;
    }

    /**
     * Run the pipeline around a core terminal handler.
     *
     * @param callable(NextRequest): NextResponse $coreHandler
     */
    public function handle(NextRequest $request, callable $coreHandler): NextResponse
    {
        $path = $request->getPath();

        // Build middleware execution stack
        $runner = $coreHandler;

        // Iterate backwards through middleware
        for ($i = count($this->middlewares) - 1; $i >= 0; $i--) {
            $entry = $this->middlewares[$i];
            $middleware = $entry['middleware'];
            $matchers = $entry['matchers'];

            // If matchers are defined, verify path matches
            if (!empty($matchers) && !$this->matchesAny($path, $matchers)) {
                continue;
            }

            $next = $runner;
            $runner = function (NextRequest $req) use ($middleware, $next): NextResponse {
                if ($middleware instanceof MiddlewareInterface) {
                    return $middleware->process($req, $next);
                }

                $res = $middleware($req, $next);
                if (!$res instanceof NextResponse) {
                    return $next($req);
                }
                return $res;
            };
        }

        return $runner($request);
    }

    /**
     * Load middleware from file (e.g. middleware.php).
     */
    public static function loadFromFile(string $filePath): ?self
    {
        if (!file_exists($filePath)) {
            return null;
        }

        $config = require $filePath;
        $pipeline = new self();

        if (is_callable($config)) {
            $pipeline->pipe($config);
            return $pipeline;
        }

        if (is_array($config)) {
            $handler = $config['handler'] ?? $config['middleware'] ?? null;
            $matchers = (array) ($config['matcher'] ?? $config['matchers'] ?? []);

            if (is_callable($handler) || $handler instanceof MiddlewareInterface) {
                $pipeline->pipe($handler, $matchers);
                return $pipeline;
            }
        }

        if ($config instanceof MiddlewareInterface) {
            $pipeline->pipe($config);
            return $pipeline;
        }

        return null;
    }

    /**
     * Checks if a path matches any matcher pattern.
     *
     * @param list<string> $matchers
     */
    public function matchesAny(string $path, array $matchers): bool
    {
        foreach ($matchers as $pattern) {
            if ($this->matchPattern($path, $pattern)) {
                return true;
            }
        }
        return false;
    }

    private function matchPattern(string $path, string $pattern): bool
    {
        // Support Next.js-style :path* or :path+ or *
        $regex = preg_quote($pattern, '#');
        $regex = str_replace(
            ['\\:path\\*', '\\:path\\+', '\\*'],
            ['.*', '.+', '.*'],
            $regex
        );

        return (bool) preg_match('#^' . $regex . '$#', $path);
    }
}
