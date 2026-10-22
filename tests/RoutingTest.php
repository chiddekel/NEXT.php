<?php

declare(strict_types=1);

namespace Next\Tests;

use Next\Http\NextRequest;
use Next\Routing\FileSystemRouter;
use Next\Routing\RouteType;
use PHPUnit\Framework\TestCase;

class RoutingTest extends TestCase
{
    private string $tempAppDir;

    protected function setUp(): void
    {
        $this->tempAppDir = sys_get_temp_dir() . '/next_test_app_' . uniqid();
        mkdir($this->tempAppDir, 0777, true);

        // Root
        file_put_contents($this->tempAppDir . '/layout.php', "<?php return fn(\$p) => 'ROOT:' . \$p['children'];");
        file_put_contents($this->tempAppDir . '/page.php', "<?php return fn() => 'HOME';");

        // Static route /about
        mkdir($this->tempAppDir . '/about', 0777, true);
        file_put_contents($this->tempAppDir . '/about/page.php', "<?php return fn() => 'ABOUT';");

        // Route Group (marketing) -> /pricing
        mkdir($this->tempAppDir . '/(marketing)/pricing', 0777, true);
        file_put_contents($this->tempAppDir . '/(marketing)/pricing/page.php', "<?php return fn() => 'PRICING';");

        // Dynamic route /blog/[slug]
        mkdir($this->tempAppDir . '/blog/[slug]', 0777, true);
        file_put_contents($this->tempAppDir . '/blog/layout.php', "<?php return fn(\$p) => 'BLOG_LAYOUT:' . \$p['children'];");
        file_put_contents($this->tempAppDir . '/blog/[slug]/page.php', "<?php return fn(\$p) => 'POST:' . \$p['params']['slug'];");

        // Specific static route /blog/new to test precedence over /blog/[slug]
        mkdir($this->tempAppDir . '/blog/new', 0777, true);
        file_put_contents($this->tempAppDir . '/blog/new/page.php', "<?php return fn() => 'NEW_POST';");

        // Catch-all route /docs/[...slug]
        mkdir($this->tempAppDir . '/docs/[...slug]', 0777, true);
        file_put_contents($this->tempAppDir . '/docs/[...slug]/page.php', "<?php return fn(\$p) => 'DOCS:' . implode('/', \$p['params']['slug']);");

        // Optional catch-all /shop/[[...categories]]
        mkdir($this->tempAppDir . '/shop/[[...categories]]', 0777, true);
        file_put_contents($this->tempAppDir . '/shop/[[...categories]]/page.php', "<?php return fn(\$p) => 'SHOP:' . count(\$p['params']['categories']);");

        // Multiple dynamic segments /store/[cat]/[item]
        mkdir($this->tempAppDir . '/store/[cat]/[item]', 0777, true);
        file_put_contents($this->tempAppDir . '/store/[cat]/[item]/page.php', "<?php return fn(\$p) => \$p['params']['cat'] . ':' . \$p['params']['item'];");

        // API route /api/users
        mkdir($this->tempAppDir . '/api/users', 0777, true);
        file_put_contents($this->tempAppDir . '/api/users/route.php', "<?php return ['GET' => fn() => Next\\Http\\NextResponse::json([])];");
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->tempAppDir);
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

    public function testRootMatch(): void
    {
        $router = new FileSystemRouter($this->tempAppDir);
        $match = $router->match(new NextRequest('GET', '/'));

        $this->assertNotNull($match);
        $this->assertSame(RouteType::PAGE, $match->getType());
        $this->assertSame('/', $match->getRoutePattern());
        $this->assertCount(1, $match->getLayoutFiles());
    }

    public function testStaticRouteMatch(): void
    {
        $router = new FileSystemRouter($this->tempAppDir);
        $match = $router->match(new NextRequest('GET', '/about'));

        $this->assertNotNull($match);
        $this->assertSame(RouteType::PAGE, $match->getType());
        $this->assertSame('/about', $match->getRoutePattern());
    }

    public function testRouteGroupPathStripping(): void
    {
        $router = new FileSystemRouter($this->tempAppDir);
        $match = $router->match(new NextRequest('GET', '/pricing'));

        $this->assertNotNull($match);
        $this->assertSame('/pricing', $match->getRoutePattern());
    }

    public function testDynamicSegmentMatch(): void
    {
        $router = new FileSystemRouter($this->tempAppDir);
        $match = $router->match(new NextRequest('GET', '/blog/solid-php'));

        $this->assertNotNull($match);
        $this->assertSame('solid-php', $match->getParam('slug'));
        // Verify layout inheritance: root layout + blog layout
        $this->assertCount(2, $match->getLayoutFiles());
    }

    public function testPrecedenceExactOverDynamic(): void
    {
        $router = new FileSystemRouter($this->tempAppDir);
        // /blog/new should match the static route, not [slug] with slug='new'
        $match = $router->match(new NextRequest('GET', '/blog/new'));

        $this->assertNotNull($match);
        $this->assertSame('/blog/new', $match->getRoutePattern());
        $this->assertEmpty($match->getParams());
    }

    public function testCatchAllRoute(): void
    {
        $router = new FileSystemRouter($this->tempAppDir);
        $match = $router->match(new NextRequest('GET', '/docs/guides/getting-started/cli'));

        $this->assertNotNull($match);
        $this->assertSame(['guides', 'getting-started', 'cli'], $match->getParam('slug'));
    }

    public function testOptionalCatchAllRoute(): void
    {
        $router = new FileSystemRouter($this->tempAppDir);

        // Without parameters
        $matchEmpty = $router->match(new NextRequest('GET', '/shop'));
        $this->assertNotNull($matchEmpty);
        $this->assertSame([], $matchEmpty->getParam('categories'));

        // With parameters
        $matchParams = $router->match(new NextRequest('GET', '/shop/clothes/shirts'));
        $this->assertNotNull($matchParams);
        $this->assertSame(['clothes', 'shirts'], $matchParams->getParam('categories'));
    }

    public function testMultipleDynamicSegments(): void
    {
        $router = new FileSystemRouter($this->tempAppDir);
        $match = $router->match(new NextRequest('GET', '/store/electronics/macbook'));

        $this->assertNotNull($match);
        $this->assertSame('electronics', $match->getParam('cat'));
        $this->assertSame('macbook', $match->getParam('item'));
    }

    public function testApiRouteMatch(): void
    {
        $router = new FileSystemRouter($this->tempAppDir);
        $match = $router->match(new NextRequest('GET', '/api/users'));

        $this->assertNotNull($match);
        $this->assertSame(RouteType::API, $match->getType());
        $this->assertSame('/api/users', $match->getRoutePattern());
    }
}
