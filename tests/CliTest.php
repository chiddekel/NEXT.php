<?php

declare(strict_types=1);

namespace Next\Tests;

use Next\Cli\Application;
use PHPUnit\Framework\TestCase;

class CliTest extends TestCase
{
    public function testRepositoryLauncherUsesBundledExample(): void
    {
        $stubDir = sys_get_temp_dir() . '/next-cli-' . bin2hex(random_bytes(8));
        mkdir($stubDir);
        // Replace only the child server command so this test needs no open port.
        file_put_contents($stubDir . '/php', "#!/bin/sh\npwd\nprintf '%s\\n' \"\$@\"\n");
        chmod($stubDir . '/php', 0755);
        $root = dirname(__DIR__);

        try {
            $process = proc_open(
                [PHP_BINARY, $root . '/bin/next', 'dev'],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                $root,
                ['PATH' => $stubDir . PATH_SEPARATOR . getenv('PATH')]
            );
            $this->assertIsResource($process);
            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $code = proc_close($process);

            $this->assertSame(0, $code, $error);
            $this->assertStringContainsString($root . '/example' . "\n", $output);
            $this->assertStringContainsString($root . '/example/.next/dev-router.php', $output);
        } finally {
            unlink($stubDir . '/php');
            rmdir($stubDir);
        }
    }

    public function testHelpAndVersion(): void
    {
        $outStream = fopen('php://memory', 'w+');
        $errStream = fopen('php://memory', 'w+');
        $app = new Application('.', $outStream, $errStream);

        $codeVersion = $app->run(['next', '--version']);
        rewind($outStream);
        $versionOutput = stream_get_contents($outStream);

        $this->assertSame(0, $codeVersion);
        $this->assertStringContainsString('NEXT.php v1.0.0', (string) $versionOutput);

        // Reset stream
        ftruncate($outStream, 0);
        rewind($outStream);

        $codeHelp = $app->run(['next', '--help']);
        rewind($outStream);
        $helpOutput = stream_get_contents($outStream);

        $this->assertSame(0, $codeHelp);
        $this->assertStringContainsString('Available Commands', (string) $helpOutput);
        $this->assertStringContainsString('dev', (string) $helpOutput);
        $this->assertStringContainsString('build', (string) $helpOutput);
        $this->assertStringContainsString('start', (string) $helpOutput);
    }

    public function testUnknownCommand(): void
    {
        $errStream = fopen('php://memory', 'w+');
        $outStream = fopen('php://memory', 'w+');
        $app = new Application('.', $outStream, $errStream);

        $code = $app->run(['next', 'foobar']);

        rewind($errStream);
        $errOutput = stream_get_contents($errStream);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('Unknown command: foobar', (string) $errOutput);
    }
}
