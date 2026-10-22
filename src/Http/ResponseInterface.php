<?php

declare(strict_types=1);

namespace Next\Http;

/**
 * Interface Segregation: Response abstraction.
 */
interface ResponseInterface
{
    public function getStatusCode(): int;

    public function getHeaders(): Headers;

    public function getHeader(string $name, ?string $default = null): ?string;

    public function getCookies(): CookieJar;

    public function getBody(): string;

    public function isRedirect(): bool;

    public function isRewrite(): bool;

    public function getRewriteUrl(): ?string;
}
