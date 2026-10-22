<?php

declare(strict_types=1);

namespace Next\Tests;

use Next\Http\NextRequest;
use Next\Http\NextResponse;
use Next\Middleware\MiddlewarePipeline;
use PHPUnit\Framework\TestCase;

class MiddlewareTest extends TestCase
{
    public function testMiddlewarePipelineExecution(): void
    {
        $pipeline = new MiddlewarePipeline();

        $pipeline->pipe(function (NextRequest $req, callable $next): NextResponse {
            $response = $next($req);
            return $response->withHeader('X-First', '1');
        });

        $pipeline->pipe(function (NextRequest $req, callable $next): NextResponse {
            $response = $next($req);
            return $response->withHeader('X-Second', '2');
        });

        $req = new NextRequest('GET', '/');
        $res = $pipeline->handle($req, function (NextRequest $r): NextResponse {
            return NextResponse::text('Terminal Handler');
        });

        $this->assertSame('Terminal Handler', $res->getBody());
        $this->assertSame('1', $res->getHeader('X-First'));
        $this->assertSame('2', $res->getHeader('X-Second'));
    }

    public function testMiddlewareShortCircuitRedirect(): void
    {
        $pipeline = new MiddlewarePipeline();

        $pipeline->pipe(function (NextRequest $req, callable $next): NextResponse {
            if (!$req->getCookie('token')) {
                return NextResponse::redirect('/login');
            }
            return $next($req);
        });

        $reqUnauth = new NextRequest('GET', '/dashboard');
        $res = $pipeline->handle($reqUnauth, function (): NextResponse {
            return NextResponse::text('Dashboard Content');
        });

        $this->assertTrue($res->isRedirect());
        $this->assertSame('/login', $res->getHeader('Location'));
    }

    public function testMiddlewarePathMatcher(): void
    {
        $pipeline = new MiddlewarePipeline();

        // Middleware only runs on /admin/*
        $pipeline->pipe(function (NextRequest $req, callable $next): NextResponse {
            $res = $next($req);
            return $res->withHeader('X-Admin-Check', 'passed');
        }, ['/admin/:path*']);

        $reqPublic = new NextRequest('GET', '/public/info');
        $resPublic = $pipeline->handle($reqPublic, fn() => NextResponse::text('public'));
        $this->assertNull($resPublic->getHeader('X-Admin-Check'));

        $reqAdmin = new NextRequest('GET', '/admin/settings');
        $resAdmin = $pipeline->handle($reqAdmin, fn() => NextResponse::text('admin'));
        $this->assertSame('passed', $resAdmin->getHeader('X-Admin-Check'));
    }
}
