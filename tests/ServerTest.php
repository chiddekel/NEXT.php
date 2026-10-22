<?php

declare(strict_types=1);

namespace Next\Tests;

use Next\Http\NextRequest;
use Next\Server\NextServer;
use Next\Server\RuntimeEnvironment;
use PHPUnit\Framework\TestCase;

class ServerTest extends TestCase
{
    private string $exampleDir;
    private NextServer $server;

    protected function setUp(): void
    {
        $this->exampleDir = dirname(__DIR__) . '/example';
        $this->server = new NextServer(
            projectDir: $this->exampleDir,
            environment: RuntimeEnvironment::DEVELOPMENT
        );
    }

    public function testHomePageRendering(): void
    {
        $req = new NextRequest('GET', '/');
        $res = $this->server->handle($req);

        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame('text/html; charset=utf-8', $res->getHeader('Content-Type'));

        $body = $res->getBody();
        $this->assertStringContainsString('The Next.js Framework for PHP', $body);
        $this->assertStringContainsString('<title>Welcome to NEXT.php</title>', $body);
        $this->assertStringContainsString('window.swup', $body); // Swup.js transition runtime
        $this->assertStringContainsString('id="swup"', $body);
        $this->assertSame('NEXT.php', $res->getHeader('X-Powered-By')); // next.config.php header
    }

    public function testBlogDynamicPageRendering(): void
    {
        $req = new NextRequest('GET', '/blog/solid-php');
        $res = $this->server->handle($req);

        $this->assertSame(200, $res->getStatusCode());
        $body = $res->getBody();
        $this->assertStringContainsString('Solid Php - NEXT.php Blog', $body);
        $this->assertStringContainsString('parameter: <strong>solid-php</strong>', $body);
        // Nested layout sidebar check
        $this->assertStringContainsString('Blog Categories', $body);
        // Middleware header
        $this->assertTrue($res->getHeaders()->has('X-Response-Time'));
    }

    public function testRouteGroupPath(): void
    {
        $req = new NextRequest('GET', '/pricing');
        $res = $this->server->handle($req);

        $this->assertSame(200, $res->getStatusCode());
        $this->assertStringContainsString('Simple, Predictable Pricing', $res->getBody());
    }

    public function testCatchAllRoute(): void
    {
        $req = new NextRequest('GET', '/docs/features/app-router');
        $res = $this->server->handle($req);

        $this->assertSame(200, $res->getStatusCode());
        $this->assertStringContainsString('features / app-router', $res->getBody());
    }

    public function testApiRouteGet(): void
    {
        $req = new NextRequest('GET', '/api/hello?name=Developer');
        $res = $this->server->handle($req);

        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame('application/json; charset=utf-8', $res->getHeader('Content-Type'));

        $data = json_decode($res->getBody(), true);
        $this->assertSame('Hello, Developer from NEXT.php API Route!', $data['message']);
        $this->assertSame('GET', $data['method']);
        $this->assertTrue($res->getHeaders()->has('X-Response-Time'));
    }

    public function testApiRoutePost(): void
    {
        $req = new NextRequest(
            method: 'POST',
            uri: '/api/hello',
            headers: ['Content-Type' => 'application/json'],
            body: '{"framework":"NEXT.php","rating":10}'
        );
        $res = $this->server->handle($req);

        $this->assertSame(201, $res->getStatusCode());
        $data = json_decode($res->getBody(), true);
        $this->assertSame('created', $data['status']);
        $this->assertSame('NEXT.php', $data['data']['framework']);
    }

    public function testDynamicApiRoute(): void
    {
        $req = new NextRequest('GET', '/api/posts/42');
        $res = $this->server->handle($req);

        $this->assertSame(200, $res->getStatusCode());
        $data = json_decode($res->getBody(), true);
        $this->assertSame('42', $data['post']['id']);
    }

    public function testPublicStaticAsset(): void
    {
        $req = new NextRequest('GET', '/robots.txt');
        $res = $this->server->handle($req);

        $this->assertSame(200, $res->getStatusCode());
        $this->assertStringContainsString('text/plain', $res->getHeader('Content-Type'));
        $this->assertStringContainsString('User-agent: *', $res->getBody());
    }

    public function testConfiguredRedirect(): void
    {
        $req = new NextRequest('GET', '/old-pricing');
        $res = $this->server->handle($req);

        $this->assertTrue($res->isRedirect());
        $this->assertSame(308, $res->getStatusCode());
        $this->assertSame('/pricing', $res->getHeader('Location'));
    }

    public function testConfiguredRewrite(): void
    {
        $req = new NextRequest('GET', '/custom-docs');
        $res = $this->server->handle($req);

        $this->assertSame(200, $res->getStatusCode());
        $this->assertStringContainsString('getting-started', $res->getBody());
    }

    public function testNotFoundHandler(): void
    {
        $req = new NextRequest('GET', '/route-does-not-exist');
        $res = $this->server->handle($req);

        $this->assertSame(404, $res->getStatusCode());
        $this->assertStringContainsString('This page could not be found.', $res->getBody());
    }

    public function testIncrementalStaticRegenerationCaching(): void
    {
        $cache = new \Next\Cache\MemoryCacheStore();
        $prodServer = new NextServer(
            projectDir: $this->exampleDir,
            environment: RuntimeEnvironment::PRODUCTION,
            cache: $cache
        );

        $req = new NextRequest('GET', '/');

        // First request: MISS
        $res1 = $prodServer->handle($req);
        $this->assertSame(200, $res1->getStatusCode());
        $this->assertSame('MISS', $res1->getHeader('X-Nextjs-Cache'));
        $this->assertTrue($cache->has('route:/'));

        // Second request: HIT
        $res2 = $prodServer->handle($req);
        $this->assertSame(200, $res2->getStatusCode());
        $this->assertSame('HIT', $res2->getHeader('X-Nextjs-Cache'));

        // Mark as stale (simulate expiration)
        $cache->set('route:/', $res1->getBody(), -10, ['page']);
        $this->assertTrue($cache->isStale('route:/'));

        // Third request: STALE-while-revalidate
        $res3 = $prodServer->handle($req);
        $this->assertSame(200, $res3->getStatusCode());
        $this->assertSame('STALE', $res3->getHeader('X-Nextjs-Cache'));

        // Fourth request: fresh HIT again
        $res4 = $prodServer->handle($req);
        $this->assertSame(200, $res4->getStatusCode());
        $this->assertSame('HIT', $res4->getHeader('X-Nextjs-Cache'));
    }

    public function testHealthCheckEndpoint(): void
    {
        $req = new NextRequest('GET', '/__health');
        $res = $this->server->handle($req);

        $this->assertSame(200, $res->getStatusCode());
        $data = json_decode($res->getBody(), true);
        $this->assertSame('healthy', $data['status']);
        $this->assertSame('NEXT.php', $data['framework']);
        $this->assertArrayHasKey('timestamp', $data);
    }

    public function testStaticAssetETagAnd304NotModified(): void
    {
        $req1 = new NextRequest('GET', '/robots.txt');
        $res1 = $this->server->handle($req1);

        $this->assertSame(200, $res1->getStatusCode());
        $etag = $res1->getHeader('ETag');
        $this->assertNotNull($etag);

        // Conditional GET with If-None-Match
        $req2 = new NextRequest('GET', '/robots.txt', ['If-None-Match' => $etag]);
        $res2 = $this->server->handle($req2);

        $this->assertSame(304, $res2->getStatusCode());
        $this->assertSame('', $res2->getBody());
    }

    public function testSecurityPathTraversalPrevented(): void
    {
        $req = new NextRequest('GET', '/../../composer.json');
        $res = $this->server->handle($req);

        // Path traversal should not serve internal files, should return 404
        $this->assertSame(404, $res->getStatusCode());
    }

    public function testProductionErrorDoesNotLeakStackTrace(): void
    {
        $renderer = new \Next\Rendering\PageRenderer();
        $req = new NextRequest('GET', '/broken');

        // Development mode: contains trace
        $devHtml = $renderer->renderError(new \Exception('SecretDBPassword'), $req, null, [], false);
        $this->assertStringContainsString('SecretDBPassword', $devHtml);
        $this->assertStringContainsString('Unhandled Server Exception', $devHtml);

        // Production mode: hides sensitive traces
        $prodHtml = $renderer->renderError(new \Exception('SecretDBPassword'), $req, null, [], true);
        $this->assertStringNotContainsString('SecretDBPassword', $prodHtml);
        $this->assertStringContainsString('500', $prodHtml);
        $this->assertStringContainsString('Internal Server Error', $prodHtml);
    }

    public function testSwupIntegrationByDefault(): void
    {
        $req = new NextRequest('GET', '/');
        $res = $this->server->handle($req);

        $body = $res->getBody();
        $this->assertStringContainsString('<style id="swup-transitions">', $body);
        $this->assertStringContainsString('html.is-animating .transition-fade', $body);
        $this->assertStringContainsString('new Swup', $body);
        $this->assertStringContainsString('window.swup =', $body);
        $this->assertStringContainsString('window.next =', $body);
    }

    public function testSwupCanBeDisabledInConfig(): void
    {
        $customConfig = new \Next\Config\NextConfig([
            'swup' => ['enabled' => false],
        ]);

        $serverWithoutSwup = new NextServer(
            projectDir: $this->exampleDir,
            config: $customConfig
        );

        $req = new NextRequest('GET', '/');
        $res = $serverWithoutSwup->handle($req);

        $body = $res->getBody();
        $this->assertStringNotContainsString('window.swup =', $body);
        $this->assertStringNotContainsString('<style id="swup-transitions">', $body);
    }
}
