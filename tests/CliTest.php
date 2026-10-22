<?php

declare(strict_types=1);

namespace Next\Tests;

use Next\Cli\Application;
use PHPUnit\Framework\TestCase;

class CliTest extends TestCase
{
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
