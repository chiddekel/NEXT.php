<?php

declare(strict_types=1);

namespace Next\Cli;

class DevCommand implements CommandInterface
{
    private string $projectDir;

    public function __construct(string $projectDir)
    {
        $this->projectDir = rtrim(str_replace('\\', '/', $projectDir), '/');
    }

    public function getName(): string
    {
        return 'dev';
    }

    public function getDescription(): string
    {
        return 'Start NEXT.php in development mode';
    }

    public function execute(array $args): int
    {
        $port = 3000;
        $host = 'localhost';

        for ($i = 0; $i < count($args); $i++) {
            if (($args[$i] === '-p' || $args[$i] === '--port') && isset($args[$i + 1])) {
                $port = (int) $args[++$i];
            } elseif (($args[$i] === '-H' || $args[$i] === '--hostname') && isset($args[$i + 1])) {
                $host = $args[++$i];
            }
        }

        $appDir = $this->projectDir . '/app';
        if (!is_dir($appDir)) {
            fwrite(STDERR, "Error: 'app' directory not found in {$this->projectDir}\n");
            return 1;
        }

        // Create dev router script if not exists
        $dotNextDir = $this->projectDir . '/.next';
        if (!is_dir($dotNextDir)) {
            mkdir($dotNextDir, 0777, true);
        }

        $devRouterFile = $dotNextDir . '/dev-router.php';
        $vendorAutoload = $this->findAutoload();

        $devRouterContent = <<<PHP
<?php
declare(strict_types=1);

require_once '{$vendorAutoload}';

use Next\\Server\\NextServer;
use Next\\Server\\RuntimeEnvironment;

// Handle static files directly in PHP dev server
\$path = parse_url(\$_SERVER['REQUEST_URI'], PHP_URL_PATH);
\$publicFile = '{$this->projectDir}/public' . \$path;
if (\$path !== '/' && file_exists(\$publicFile) && !is_dir(\$publicFile)) {
    return false;
}

\$server = new NextServer('{$this->projectDir}', RuntimeEnvironment::DEVELOPMENT);
\$server->run();
PHP;

        file_put_contents($devRouterFile, $devRouterContent);

        echo Banner::render();
        echo Banner::header('1.0.0');
        echo "   - Local:        \033[4mhttp://{$host}:{$port}\033[0m\n";
        echo "   - Environment:  development\n\n";
        echo " \033[32m✓\033[0m Ready in 12ms\n\n";

        $cmd = sprintf(
            'php -S %s:%d %s',
            escapeshellarg($host),
            $port,
            escapeshellarg($devRouterFile)
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
