<?php

declare(strict_types=1);

namespace Next\Http;

use DateTimeInterface;

/**
 * Next.js-compatible HTTP Response.
 */
class NextResponse implements ResponseInterface
{
    private int $statusCode;
    private Headers $headers;
    private CookieJar $cookies;
    private string $body;
    private bool $isRedirect = false;
    private bool $isRewrite = false;
    private ?string $rewriteUrl = null;

    public function __construct(
        string $body = '',
        int $statusCode = 200,
        array|Headers $headers = [],
        array|CookieJar $cookies = []
    ) {
        $this->body = $body;
        $this->statusCode = $statusCode;
        $this->headers = $headers instanceof Headers ? clone $headers : new Headers($headers);
        $this->cookies = $cookies instanceof CookieJar ? clone $cookies : new CookieJar($cookies);
    }

    public static function json(mixed $data, int $status = 200, array $headers = []): self
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $headers = array_merge(['Content-Type' => 'application/json; charset=utf-8'], $headers);
        return new self($json, $status, $headers);
    }

    public static function html(string $html, int $status = 200, array $headers = []): self
    {
        $headers = array_merge(['Content-Type' => 'text/html; charset=utf-8'], $headers);
        return new self($html, $status, $headers);
    }

    public static function text(string $text, int $status = 200, array $headers = []): self
    {
        $headers = array_merge(['Content-Type' => 'text/plain; charset=utf-8'], $headers);
        return new self($text, $status, $headers);
    }

    public static function redirect(string $url, int $status = 307): self
    {
        $res = new self('', $status, ['Location' => $url]);
        $res->isRedirect = true;
        return $res;
    }

    public static function rewrite(string $destinationUrl, array $headers = []): self
    {
        $res = new self('', 200, $headers);
        $res->isRewrite = true;
        $res->rewriteUrl = $destinationUrl;
        return $res;
    }

    public static function next(array $headers = []): self
    {
        // Special marker response in middleware pipeline to proceed
        return new self('', 200, $headers);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function withStatus(int $code): self
    {
        $clone = clone $this;
        $clone->statusCode = $code;
        return $clone;
    }

    public function getHeaders(): Headers
    {
        return $this->headers;
    }

    public function getHeader(string $name, ?string $default = null): ?string
    {
        return $this->headers->get($name, $default);
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers = clone $this->headers;
        $clone->headers->set($name, $value);
        return $clone;
    }

    public function getCookies(): CookieJar
    {
        return $this->cookies;
    }

    public function cookie(
        string $name,
        string $value,
        int|DateTimeInterface|null $expires = null,
        string $path = '/',
        ?string $domain = null,
        bool $secure = false,
        bool $httpOnly = true,
        ?string $sameSite = 'Lax'
    ): self {
        $cookie = new Cookie($name, $value, $expires, $path, $domain, $secure, $httpOnly, $sameSite);
        $this->cookies->set($cookie);
        return $this;
    }

    public function deleteCookie(string $name, string $path = '/', ?string $domain = null): self
    {
        $this->cookies->delete($name, $path, $domain);
        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function withBody(string $body): self
    {
        $clone = clone $this;
        $clone->body = $body;
        return $clone;
    }

    public function isRedirect(): bool
    {
        return $this->isRedirect;
    }

    public function isRewrite(): bool
    {
        return $this->isRewrite;
    }

    public function getRewriteUrl(): ?string
    {
        return $this->rewriteUrl;
    }
}
