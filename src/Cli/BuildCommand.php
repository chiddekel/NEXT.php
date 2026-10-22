<?php

declare(strict_types=1);

namespace Next\Cli;

use Next\Http\NextRequest;
use Next\Rendering\PageRenderer;
use Next\Routing\FileSystemRouter;
use Next\Routing\RouteType;

class BuildCommand implements CommandInterface
{
    private string $projectDir;

    public function __construct(string $projectDir)
    {
        $this->projectDir = rtrim(str_replace('\\', '/', $projectDir), '/');
    }

    public function getName(): string
    {
        return 'build';
    }

    public function getDescription(): string
    {
        return 'Create an optimized production build of NEXT.php';
    }

    public function execute(array $args): int
    {
        $startTime = microtime(true);
        $appDir = $this->projectDir . '/app';

        if (!is_dir($appDir)) {
            fwrite(STDERR, "Error: 'app' directory not found in {$this->projectDir}\n");
            return 1;
        }

        echo Banner::render();
        echo Banner::header('1.0.0');
        echo "   Creating an optimized production build ...\n\n";

        $dotNextDir = $this->projectDir . '/.next';
        $staticDir = $dotNextDir . '/static';
        if (!is_dir($staticDir)) {
            mkdir($staticDir, 0777, true);
        }

        $router = new FileSystemRouter($appDir);
        $routes = $router->getCompiledRoutes();

        $renderer = new PageRenderer();
        $manifest = [
            'version' => 1,
            'pages' => [],
            'api' => [],
        ];

        $tableRows = [];

        foreach ($routes as $route) {
            $pattern = $route['pattern'];
            $isDynamic = !empty($route['paramNames']);
            $isApi = ($route['type'] === RouteType::API);

            if ($isApi) {
                $typeSymbol = "\033[36mƒ\033[0m"; // ƒ (API)
                $typeLabel = 'API';
                $size = '0 B';
                $manifest['api'][] = [
                    'pattern' => $pattern,
                    'file' => $route['filePath'],
                ];
            } elseif ($isDynamic) {
                $typeSymbol = "\033[33mλ\033[0m"; // λ (Server)
                $typeLabel = 'Server';
                $size = '1.2 kB';
                $manifest['pages'][] = [
                    'pattern' => $pattern,
                    'file' => $route['filePath'],
                    'static' => false,
                ];
            } else {
                $typeSymbol = "\033[32m○\033[0m"; // ○ (Static)
                $typeLabel = 'Static';

                // Pre-render static page
                $req = new NextRequest('GET', $pattern);
                $match = $router->match($req);
                $html = '';
                if ($match !== null) {
                    try {
                        $html = $renderer->render($match, $req);
                    } catch (\Throwable $e) {
                        $html = "<!-- Pre-render failed: {$e->getMessage()} -->";
                    }
                }

                $staticFile = $staticDir . ($pattern === '/' ? '/index.html' : $pattern . '.html');
                $staticFileDir = dirname($staticFile);
                if (!is_dir($staticFileDir)) {
                    mkdir($staticFileDir, 0777, true);
                }
                file_put_contents($staticFile, $html);

                $bytes = strlen($html);
                $size = $bytes < 1024 ? "{$bytes} B" : sprintf('%.1f kB', $bytes / 1024);

                $manifest['pages'][] = [
                    'pattern' => $pattern,
                    'file' => $route['filePath'],
                    'static' => true,
                    'staticHtml' => $staticFile,
                ];
            }

            $tableRows[] = [
                'symbol' => $typeSymbol,
                'pattern' => $pattern,
                'size' => $size,
            ];
        }

        // Save routes manifest
        file_put_contents(
            $dotNextDir . '/routes-manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        echo " Route (app)                              Size\n";
        echo " ┌───────────────────────────────────────┬─────────┐\n";
        foreach ($tableRows as $row) {
            printf(
                " │ %s %-37s │ %7s │\n",
                $row['symbol'],
                $row['pattern'],
                $row['size']
            );
        }
        echo " └───────────────────────────────────────┴─────────┘\n\n";

        echo " \033[32m○\033[0m  (Static)   prerendered as static content\n";
        echo " \033[33mλ\033[0m  (Server)   server-rendered on demand\n";
        echo " \033[36mƒ\033[0m  (API)      API route handler\n\n";

        $duration = round((microtime(true) - $startTime), 2);
        echo " \033[32m✓\033[0m Compiled successfully in {$duration}s\n\n";

        return 0;
    }
}
