<?php

declare(strict_types=1);

namespace Next\Tests;

use Next\Http\NextRequest;
use Next\Rendering\PageRenderer;
use Next\Rendering\ViewContext;
use Next\Routing\RouteMatch;
use Next\Routing\RouteType;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class RenderingTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/next_render_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->tempDir);
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? $this->deleteDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testPageRenderWithLayoutHierarchyAndMetadata(): void
    {
        $rootLayout = $this->tempDir . '/root-layout.php';
        file_put_contents($rootLayout, <<<'PHP'
<?php
use Next\Rendering\ViewContext;
return function(array $props, ViewContext $ctx): string {
    return '<html><head></head><body><div id="root">' . $props['children'] . '</div></body></html>';
};
PHP);

        $subLayout = $this->tempDir . '/sub-layout.php';
        file_put_contents($subLayout, <<<'PHP'
<?php
use Next\Rendering\ViewContext;
return function(array $props, ViewContext $ctx): string {
    return '<section class="sub">' . $props['children'] . '</section>';
};
PHP);

        $page = $this->tempDir . '/page.php';
        file_put_contents($page, <<<'PHP'
<?php
use Next\Rendering\ViewContext;
return function(array $props, ViewContext $ctx): string {
    $ctx->setTitle('Test Page Title');
    $ctx->setDescription('Page Description');
    return '<h1>Hello ' . ($props['params']['name'] ?? 'World') . '</h1>';
};
PHP);

        $match = new RouteMatch(
            type: RouteType::PAGE,
            filePath: $page,
            params: ['name' => 'Antigravity'],
            routePattern: '/hello/[name]',
            layoutFiles: [$rootLayout, $subLayout]
        );

        $req = new NextRequest('GET', '/hello/Antigravity');
        $renderer = new PageRenderer();
        $html = $renderer->render($match, $req);

        $this->assertStringContainsString('<title>Test Page Title</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="Page Description">', $html);
        $this->assertStringContainsString('<div id="root"><section class="sub"><h1>Hello Antigravity</h1></section></div>', $html);
    }

    public function testNotFoundFallback(): void
    {
        $renderer = new PageRenderer();
        $req = new NextRequest('GET', '/non-existent');
        $html = $renderer->renderNotFound($req);

        $this->assertStringContainsString('404', $html);
        $this->assertStringContainsString('This page could not be found.', $html);
    }

    public function testErrorBoundary(): void
    {
        $errorFile = $this->tempDir . '/error.php';
        file_put_contents($errorFile, <<<'PHP'
<?php
return function(array $props): string {
    return '<div class="custom-error">' . $props['error']->getMessage() . '</div>';
};
PHP);

        $renderer = new PageRenderer();
        $req = new NextRequest('GET', '/faulty');
        $html = $renderer->renderError(new RuntimeException('Database connection failed'), $req, $errorFile);

        $this->assertStringContainsString('<div class="custom-error">Database connection failed</div>', $html);
    }
}
