<?php

declare(strict_types=1);

namespace Next\Http;

use DateTimeInterface;

/**
 * Represents a single HTTP Cookie.
 */
class Cookie
{
    public function __construct(
        private readonly string $name,
        private readonly string $value,
        private readonly int|DateTimeInterface|null $expires = null,
        private readonly string $path = '/',
        private readonly ?string $domain = null,
        private readonly bool $secure = false,
        private readonly bool $httpOnly = true,
        private readonly ?string $sameSite = 'Lax',
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getExpires(): int|DateTimeInterface|null
    {
        return $this->expires;
    }

    public function getExpiresTimestamp(): ?int
    {
        if ($this->expires instanceof DateTimeInterface) {
            return $this->expires->getTimestamp();
        }
        return $this->expires;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getDomain(): ?string
    {
        return $this->domain;
    }

    public function isSecure(): bool
    {
        return $this->secure;
    }

    public function isHttpOnly(): bool
    {
        return $this->httpOnly;
    }

    public function getSameSite(): ?string
    {
        return $this->sameSite;
    }

    public function toHeaderString(): string
    {
        $parts = [rawurlencode($this->name) . '=' . rawurlencode($this->value)];

        if ($this->expires !== null) {
            $timestamp = $this->getExpiresTimestamp();
            if ($timestamp !== null) {
                $parts[] = 'Expires=' . gmdate('D, d M Y H:i:s T', $timestamp);
                $maxAge = max(0, $timestamp - time());
                $parts[] = 'Max-Age=' . $maxAge;
            }
        }

        if ($this->path !== '') {
            $parts[] = 'Path=' . $this->path;
        }

        if ($this->domain !== null && $this->domain !== '') {
            $parts[] = 'Domain=' . $this->domain;
        }

        if ($this->secure) {
            $parts[] = 'Secure';
        }

        if ($this->httpOnly) {
            $parts[] = 'HttpOnly';
        }

        if ($this->sameSite !== null) {
            $parts[] = 'SameSite=' . ucfirst(strtolower($this->sameSite));
        }

        return implode('; ', $parts);
    }
}
