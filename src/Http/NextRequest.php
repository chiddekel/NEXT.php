<?php

declare(strict_types=1);

namespace Next\Http;

use RuntimeException;

/**
 * Next.js-compatible HTTP Request.
 */
class NextRequest implements RequestInterface
{
    private string $method;
    private string $uri;
    private string $path;
    private Headers $headers;
    private array $queryParams;
    private CookieJar $cookies;
    private string $body;
    private ?array $parsedJson = null;
    private array $serverParams;

    public function __construct(
        string $method,
        string $uri,
        array|Headers $headers = [],
        string $body = '',
        array|CookieJar $cookies = [],
        array $queryParams = [],
        array $serverParams = []
    ) {
        $this->method = strtoupper($method);
        $this->uri = $uri;
        $this->path = (string) (parse_url($uri, PHP_URL_PATH) ?? '/');
        $this->headers = $headers instanceof Headers ? $headers : new Headers($headers);
        $this->body = $body;
        $this->cookies = $cookies instanceof CookieJar ? $cookies : new CookieJar($cookies);

        if (empty($queryParams) && ($query = parse_url($uri, PHP_URL_QUERY))) {
            parse_str($query, $parsed);
            $this->queryParams = $parsed;
        } else {
            $this->queryParams = $queryParams;
        }

        $this->serverParams = $serverParams;
    }

    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        // Extract headers from $_SERVER
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = str_replace('_', '-', substr($key, 5));
                $headers[$headerName] = (string) $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                $headerName = str_replace('_', '-', $key);
                $headers[$headerName] = (string) $value;
            }
        }

        $cookies = new CookieJar();
        foreach ($_COOKIE as $k => $v) {
            $cookies->set(new Cookie((string)$k, (string)$v));
        }

        $body = (string) file_get_contents('php://input');

        return new self(
            method: $method,
            uri: $uri,
            headers: new Headers($headers),
            body: $body,
            cookies: $cookies,
            queryParams: $_GET,
            serverParams: $_SERVER
        );
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getHeaders(): Headers
    {
        return $this->headers;
    }

    public function getHeader(string $name, ?string $default = null): ?string
    {
        return $this->headers->get($name, $default);
    }

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function getQuery(string $name, mixed $default = null): mixed
    {
        return $this->queryParams[$name] ?? $default;
    }

    public function getCookies(): CookieJar
    {
        return $this->cookies;
    }

    public function getCookie(string $name, ?string $default = null): ?string
    {
        return $this->cookies->getValue($name, $default);
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function json(bool $assoc = true): mixed
    {
        if ($this->parsedJson !== null) {
            return $this->parsedJson;
        }

        if ($this->body === '') {
            return null;
        }

        $decoded = json_decode($this->body, $assoc);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Failed to parse JSON body: ' . json_last_error_msg());
        }

        if ($assoc && is_array($decoded)) {
            $this->parsedJson = $decoded;
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    public function formData(): array
    {
        if (!empty($_POST)) {
            return $_POST;
        }

        $contentType = $this->getHeader('Content-Type') ?? '';
        if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
            parse_str($this->body, $parsed);
            return $parsed;
        }

        return [];
    }

    public function getIp(): ?string
    {
        return $this->headers->get('X-Forwarded-For')
            ?? $this->headers->get('X-Real-IP')
            ?? ($this->serverParams['REMOTE_ADDR'] ?? null);
    }

    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    public function withPath(string $path): static
    {
        $clone = clone $this;
        $clone->path = $path;
        return $clone;
    }
}
