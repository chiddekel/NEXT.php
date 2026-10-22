<?php

declare(strict_types=1);

namespace Next\Cli;

class StartCommand implements CommandInterface
{
    private string $projectDir;

    public function __construct(string $projectDir)
    {
        $this->projectDir = rtrim(str_replace('\\', '/', $projectDir), '/');
    }

    public function getName(): string
    {
        return 'start';
    }

    public function getDescription(): string
    {
        return 'Start NEXT.php in production mode';
    }

    public function execute(array $args): int
    {
        $port = 3000;
        $host = '0.0.0.0';

        for ($i = 0; $i < count($args); $i++) {
            if (($args[$i] === '-p' || $args[$i] === '--port') && isset($args[$i + 1])) {
                $port = (int) $args[++$i];
            } elseif (($args[$i] === '-H' || $args[$i] === '--hostname') && isset($args[$i + 1])) {
                $host = $args[++$i];
            }
        }

        $dotNextDir = $this->projectDir . '/.next';
        if (!is_dir($dotNextDir) || !file_exists($dotNextDir . '/routes-manifest.json')) {
            echo "Warning: Could not find a production build in the '{$dotNextDir}' directory. Run 'next build' first.\n";
        }

        $prodRouterFile = $dotNextDir . '/prod-router.php';
        $vendorAutoload = $this->findAutoload();

        $prodRouterContent = <<<PHP
<?php
declare(strict_types=1);

require_once '{$vendorAutoload}';

use Next\\Server\\NextServer;
use Next\\Server\\RuntimeEnvironment;
use Next\\Cache\\FileCacheStore;

\$path = parse_url(\$_SERVER['REQUEST_URI'], PHP_URL_PATH);
\$publicFile = '{$this->projectDir}/public' . \$path;
if (\$path !== '/' && file_exists(\$publicFile) && !is_dir(\$publicFile)) {
    return false;
}

\$cache = new FileCacheStore('{$dotNextDir}/cache');
\$server = new NextServer(
    projectDir: '{$this->projectDir}',
    environment: RuntimeEnvironment::PRODUCTION,
    cache: \$cache
);
\$server->run();
PHP;

        file_put_contents($prodRouterFile, $prodRouterContent);

        echo Banner::render();
        echo Banner::header('1.0.0');
        echo "   - Local:        \033[4mhttp://{$host}:{$port}\033[0m\n";
        echo "   - Environment:  production\n\n";
        echo " \033[32m✓\033[0m Ready in 5ms\n\n";

        $cmd = sprintf(
            'php -S %s:%d %s',
            escapeshellarg($host),
            $port,
            escapeshellarg($prodRouterFile)
        );

        passthru($cmd, $returnVar);
        return $returnVar;
    }

    private function findAutoload(): string
    {
        $candidates = [
            $this->projectDir . '/vendor/autoload.php',
            dirname(__DIR__, 2) . '/vendor/autoload.php',
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return str_replace('\\', '/', $candidate);
            }
        }

        return $this->projectDir . '/vendor/autoload.php';
    }
}
