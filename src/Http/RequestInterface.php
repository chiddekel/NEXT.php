<?php

declare(strict_types=1);

namespace Next\Http;

/**
 * Interface Segregation: Request abstraction.
 */
interface RequestInterface
{
    public function getMethod(): string;

    public function getUri(): string;

    public function getPath(): string;

    public function getHeaders(): Headers;

    public function getHeader(string $name, ?string $default = null): ?string;

    public function getQueryParams(): array;

    public function getQuery(string $name, mixed $default = null): mixed;

    public function getCookies(): CookieJar;

    public function getCookie(string $name, ?string $default = null): ?string;

    public function getBody(): string;

    public function json(bool $assoc = true): mixed;

    public function getServerParams(): array;

    public function withPath(string $path): static;
}
