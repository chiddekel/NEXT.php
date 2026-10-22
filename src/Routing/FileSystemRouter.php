<?php

declare(strict_types=1);

namespace Next\Routing;

use Next\Http\RequestInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Next.js App Router File-System Router implementation.
 *
 * Implements SOLID principles:
 * - Single Responsibility: Mapping file-system hierarchy to URL patterns and route resolution.
 * - Open/Closed: Extensible via RouterInterface.
 */
class FileSystemRouter implements RouterInterface
{
    private string $appDir;
    /** @var list<array{
     *   type: RouteType,
     *   filePath: string,
     *   pattern: string,
     *   regex: string,
     *   paramNames: list<string>,
     *   catchAlls: list<string>,
     *   optionalCatchAlls: list<string>,
     *   priority: int,
     *   layoutFiles: list<string>,
     *   loadingFile: ?string,
     *   errorFile: ?string,
     *   notFoundFile: ?string
     * }>
     */
    private ?array $compiledRoutes = null;

    public function __construct(string $appDir)
    {
        $this->appDir = rtrim(str_replace('\\', '/', $appDir), '/');
    }

    public function getAppDir(): string
    {
        return $this->appDir;
    }

    public function match(RequestInterface $request): ?RouteMatch
    {
        $routes = $this->getCompiledRoutes();
        $path = '/' . trim($request->getPath(), '/');
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        foreach ($routes as $route) {
            if (preg_match($route['regex'], $path, $matches)) {
                $params = [];
                foreach ($route['paramNames'] as $paramName) {
                    if (isset($matches[$paramName]) && $matches[$paramName] !== '') {
                        if (in_array($paramName, $route['catchAlls'], true) || in_array($paramName, $route['optionalCatchAlls'], true)) {
                            $params[$paramName] = explode('/', trim($matches[$paramName], '/'));
                        } else {
                            $params[$paramName] = urldecode($matches[$paramName]);
                        }
                    } elseif (in_array($paramName, $route['optionalCatchAlls'], true)) {
                        $params[$paramName] = [];
                    }
                }

                return new RouteMatch(
                    type: $route['type'],
                    filePath: $route['filePath'],
                    params: $params,
                    routePattern: $route['pattern'],
                    layoutFiles: $route['layoutFiles'],
                    loadingFile: $route['loadingFile'],
                    errorFile: $route['errorFile'],
                    notFoundFile: $route['notFoundFile']
                );
            }
        }

        return null;
    }

    /**
     * @return list<RouteMatch>
     */
    public function getRoutes(): array
    {
        $routes = $this->getCompiledRoutes();
        $matches = [];
        foreach ($routes as $r) {
            $matches[] = new RouteMatch(
                type: $r['type'],
                filePath: $r['filePath'],
                params: [],
                routePattern: $r['pattern'],
                layoutFiles: $r['layoutFiles'],
                loadingFile: $r['loadingFile'],
                errorFile: $r['errorFile'],
                notFoundFile: $r['notFoundFile']
            );
        }
        return $matches;
    }

    /**
     * Scan the app directory and compile routes.
     *
     * @return list<array{
     *   type: RouteType,
     *   filePath: string,
     *   pattern: string,
     *   regex: string,
     *   paramNames: list<string>,
     *   catchAlls: list<string>,
     *   optionalCatchAlls: list<string>,
     *   priority: int,
     *   layoutFiles: list<string>,
     *   loadingFile: ?string,
     *   errorFile: ?string,
     *   notFoundFile: ?string
     * }>
     */
    public function getCompiledRoutes(): array
    {
        if ($this->compiledRoutes !== null) {
            return $this->compiledRoutes;
        }

        if (!is_dir($this->appDir)) {
            $this->compiledRoutes = [];
            return [];
        }

        $routes = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->appDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $fileName = $file->getFilename();
            if ($fileName !== 'page.php' && $fileName !== 'route.php') {
                continue;
            }

            $type = ($fileName === 'page.php') ? RouteType::PAGE : RouteType::API;
            $filePath = str_replace('\\', '/', $file->getPathname());
            $relPath = substr($filePath, strlen($this->appDir) + 1);

            // Directory path relative to appDir
            $dirRelPath = dirname($relPath);
            if ($dirRelPath === '.') {
                $dirRelPath = '';
            }

            $routeInfo = $this->processRouteDirectory($dirRelPath, $type, $filePath);
            $routes[] = $routeInfo;
        }

        // Sort routes by priority descending (static > dynamic > catch-all > optional catch-all)
        usort($routes, function (array $a, array $b): int {
            if ($a['priority'] === $b['priority']) {
                // Deeper exact paths come first
                return strlen($b['pattern']) <=> strlen($a['pattern']);
            }
            return $b['priority'] <=> $a['priority'];
        });

        $this->compiledRoutes = $routes;
        return $this->compiledRoutes;
    }

    /**
     * @return array{
     *   type: RouteType,
     *   filePath: string,
     *   pattern: string,
     *   regex: string,
     *   paramNames: list<string>,
     *   catchAlls: list<string>,
     *   optionalCatchAlls: list<string>,
     *   priority: int,
     *   layoutFiles: list<string>,
     *   loadingFile: ?string,
     *   errorFile: ?string,
     *   notFoundFile: ?string
     * }
     */
    private function processRouteDirectory(string $dirRelPath, RouteType $type, string $filePath): array
    {
        $segments = ($dirRelPath === '') ? [] : explode('/', $dirRelPath);

        $urlSegments = [];
        $paramNames = [];
        $catchAlls = [];
        $optionalCatchAlls = [];
        $priority = 1000;

        foreach ($segments as $segment) {
            // Check for route groups e.g. (marketing), (auth)
            if (preg_match('/^\((.+)\)$/', $segment)) {
                // Route group does not appear in URL path
                continue;
            }

            // Check for optional catch-all e.g. [[...slug]]
            if (preg_match('/^\[\[\.\.\.([a-zA-Z0-9_]+)\]\]$/', $segment, $m)) {
                $paramNames[] = $m[1];
                $optionalCatchAlls[] = $m[1];
                $urlSegments[] = [
                    'type' => 'optional_catch_all',
                    'name' => $m[1],
                    'raw' => $segment,
                ];
                $priority -= 100;
                continue;
            }

            // Check for catch-all e.g. [...slug]
            if (preg_match('/^\[\.\.\.([a-zA-Z0-9_]+)\]$/', $segment, $m)) {
                $paramNames[] = $m[1];
                $catchAlls[] = $m[1];
                $urlSegments[] = [
                    'type' => 'catch_all',
                    'name' => $m[1],
                    'raw' => $segment,
                ];
                $priority -= 50;
                continue;
            }

            // Check for dynamic segment e.g. [id]
            if (preg_match('/^\[([a-zA-Z0-9_]+)\]$/', $segment, $m)) {
                $paramNames[] = $m[1];
                $urlSegments[] = [
                    'type' => 'dynamic',
                    'name' => $m[1],
                    'raw' => $segment,
                ];
                $priority -= 10;
                continue;
            }

            // Static segment
            $urlSegments[] = [
                'type' => 'static',
                'value' => $segment,
                'raw' => $segment,
            ];
            $priority += 20;
        }

        // Build pattern and regex
        $patternParts = [];
        $regexParts = [];

        foreach ($urlSegments as $seg) {
            if ($seg['type'] === 'static') {
                $patternParts[] = $seg['value'];
                $regexParts[] = preg_quote($seg['value'], '#');
            } elseif ($seg['type'] === 'dynamic') {
                $patternParts[] = '[' . $seg['name'] . ']';
                $regexParts[] = '(?P<' . $seg['name'] . '>[^/]+)';
            } elseif ($seg['type'] === 'catch_all') {
                $patternParts[] = '[...' . $seg['name'] . ']';
                $regexParts[] = '(?P<' . $seg['name'] . '>.+)';
            } elseif ($seg['type'] === 'optional_catch_all') {
                $patternParts[] = '[[...' . $seg['name'] . ']]';
                // Handled specially at end
            }
        }

        $pattern = '/' . implode('/', $patternParts);
        if ($pattern !== '/' && str_ends_with($pattern, '/')) {
            $pattern = rtrim($pattern, '/');
        }

        // Handle optional catch-all regex construction
        $hasOptionalCatchAll = !empty($optionalCatchAlls);
        if ($hasOptionalCatchAll) {
            $optName = $optionalCatchAlls[0];
            $baseRegex = empty($regexParts) ? '' : '/' . implode('/', $regexParts);
            $regex = '#^' . $baseRegex . '(?:/(?P<' . $optName . '>.+))?$#';
        } else {
            $regex = empty($regexParts) ? '#^/$#' : '#^/' . implode('/', $regexParts) . '$#';
        }

        // Resolve layouts and boundary files for this route
        $hierarchy = $this->resolveHierarchy($segments);

        return [
            'type' => $type,
            'filePath' => $filePath,
            'pattern' => $pattern,
            'regex' => $regex,
            'paramNames' => $paramNames,
            'catchAlls' => $catchAlls,
            'optionalCatchAlls' => $optionalCatchAlls,
            'priority' => $priority,
            'layoutFiles' => $hierarchy['layouts'],
            'loadingFile' => $hierarchy['loading'],
            'errorFile' => $hierarchy['error'],
            'notFoundFile' => $hierarchy['notFound'],
        ];
    }

    /**
     * Resolves layout hierarchy from root app dir down to the target route folder.
     *
     * @param list<string> $segments Directory segments relative to appDir
     * @return array{layouts: list<string>, loading: ?string, error: ?string, notFound: ?string}
     */
    private function resolveHierarchy(array $segments): array
    {
        $layouts = [];
        $loading = null;
        $error = null;
        $notFound = null;

        $currentPath = $this->appDir;

        // Check root app/ files
        if (file_exists($currentPath . '/layout.php')) {
            $layouts[] = $currentPath . '/layout.php';
        }
        if (file_exists($currentPath . '/loading.php')) {
            $loading = $currentPath . '/loading.php';
        }
        if (file_exists($currentPath . '/error.php')) {
            $error = $currentPath . '/error.php';
        }
        if (file_exists($currentPath . '/not-found.php')) {
            $notFound = $currentPath . '/not-found.php';
        }

        // Traverse down segments
        foreach ($segments as $segment) {
            $currentPath .= '/' . $segment;

            if (file_exists($currentPath . '/layout.php')) {
                $layouts[] = $currentPath . '/layout.php';
            }
            if (file_exists($currentPath . '/loading.php')) {
                $loading = $currentPath . '/loading.php';
            }
            if (file_exists($currentPath . '/error.php')) {
                $error = $currentPath . '/error.php';
            }
            if (file_exists($currentPath . '/not-found.php')) {
                $notFound = $currentPath . '/not-found.php';
            }
        }

        return [
            'layouts' => $layouts,
            'loading' => $loading,
            'error' => $error,
            'notFound' => $notFound,
        ];
    }
}
