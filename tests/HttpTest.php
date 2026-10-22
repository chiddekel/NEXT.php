<?php

declare(strict_types=1);

namespace Next\Tests;

use Next\Http\Cookie;
use Next\Http\CookieJar;
use Next\Http\Headers;
use Next\Http\NextRequest;
use Next\Http\NextResponse;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class HttpTest extends TestCase
{
    public function testHeadersCaseInsensitive(): void
    {
        $headers = new Headers([
            'Content-Type' => 'application/json',
            'X-Custom' => 'Value1',
        ]);

        $this->assertTrue($headers->has('content-type'));
        $this->assertTrue($headers->has('CONTENT-TYPE'));
        $this->assertSame('application/json', $headers->get('content-type'));
        $this->assertSame('Value1', $headers->get('x-custom'));

        $headers->set('x-custom', 'Value2');
        $this->assertSame('Value2', $headers->get('X-Custom'));

        $headers->append('X-List', 'item1');
        $headers->append('x-list', 'item2');
        $this->assertSame(['item1', 'item2'], $headers->getAll('X-List'));

        $headers->delete('x-list');
        $this->assertFalse($headers->has('x-list'));
    }

    public function testCookieHeaderString(): void
    {
        $cookie = new Cookie(
            name: 'session_id',
            value: 'xyz123',
            expires: 1800000000,
            path: '/app',
            domain: 'example.com',
            secure: true,
            httpOnly: true,
            sameSite: 'Strict'
        );

        $headerStr = $cookie->toHeaderString();
        $this->assertStringContainsString('session_id=xyz123', $headerStr);
        $this->assertStringContainsString('Path=/app', $headerStr);
        $this->assertStringContainsString('Domain=example.com', $headerStr);
        $this->assertStringContainsString('Secure', $headerStr);
        $this->assertStringContainsString('HttpOnly', $headerStr);
        $this->assertStringContainsString('SameSite=Strict', $headerStr);
        $this->assertStringContainsString('Expires=', $headerStr);
    }

    public function testCookieJarFromHeader(): void
    {
        $header = 'user=alice; theme=dark; token=secret%20123';
        $jar = CookieJar::fromHeader($header);

        $this->assertTrue($jar->has('user'));
        $this->assertSame('alice', $jar->getValue('user'));
        $this->assertSame('dark', $jar->getValue('theme'));
        $this->assertSame('secret 123', $jar->getValue('token'));
        $this->assertNull($jar->getValue('missing'));

        $jar->delete('user');
        $deleted = $jar->get('user');
        $this->assertNotNull($deleted);
        $this->assertSame('', $deleted->getValue());
        $this->assertLessThan(time(), $deleted->getExpiresTimestamp());
    }

    public function testNextRequestParsing(): void
    {
        $req = new NextRequest(
            method: 'POST',
            uri: 'https://example.com/api/test?filter=active&sort=desc',
            headers: [
                'Content-Type' => 'application/json',
                'X-Forwarded-For' => '203.0.113.195',
            ],
            body: '{"name":"Alice","role":"admin"}',
            cookies: ['auth' => 'valid-token']
        );

        $this->assertSame('POST', $req->getMethod());
        $this->assertSame('/api/test', $req->getPath());
        $this->assertSame('active', $req->getQuery('filter'));
        $this->assertSame('desc', $req->getQuery('sort'));
        $this->assertSame('valid-token', $req->getCookie('auth'));
        $this->assertSame('203.0.113.195', $req->getIp());

        $data = $req->json();
        $this->assertIsArray($data);
        $this->assertSame('Alice', $data['name']);
        $this->assertSame('admin', $data['role']);

        $cloned = $req->withPath('/api/v2/test');
        $this->assertSame('/api/v2/test', $cloned->getPath());
        $this->assertSame('/api/test', $req->getPath());
    }

    public function testNextRequestMalformedJson(): void
    {
        $req = new NextRequest('POST', '/api', [], '{invalid json}');
        $this->expectException(RuntimeException::class);
        $req->json();
    }

    public function testNextResponseFactories(): void
    {
        $jsonRes = NextResponse::json(['hello' => 'world'], 201, ['X-Test' => '1']);
        $this->assertSame(201, $jsonRes->getStatusCode());
        $this->assertSame('{"hello":"world"}', $jsonRes->getBody());
        $this->assertSame('application/json; charset=utf-8', $jsonRes->getHeader('Content-Type'));
        $this->assertSame('1', $jsonRes->getHeader('X-Test'));

        $htmlRes = NextResponse::html('<h1>Hello</h1>');
        $this->assertSame(200, $htmlRes->getStatusCode());
        $this->assertSame('<h1>Hello</h1>', $htmlRes->getBody());
        $this->assertSame('text/html; charset=utf-8', $htmlRes->getHeader('Content-Type'));

        $redirectRes = NextResponse::redirect('/dashboard', 308);
        $this->assertTrue($redirectRes->isRedirect());
        $this->assertSame(308, $redirectRes->getStatusCode());
        $this->assertSame('/dashboard', $redirectRes->getHeader('Location'));

        $rewriteRes = NextResponse::rewrite('/internal/dest');
        $this->assertTrue($rewriteRes->isRewrite());
        $this->assertSame('/internal/dest', $rewriteRes->getRewriteUrl());

        $cookieRes = $htmlRes->cookie('logged_in', 'true', time() + 3600);
        $this->assertTrue($cookieRes->getCookies()->has('logged_in'));
    }
}
