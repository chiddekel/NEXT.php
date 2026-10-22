<?php

declare(strict_types=1);

namespace Next\Http;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Collection of HTTP cookies.
 */
class CookieJar implements IteratorAggregate, Countable
{
    /** @var array<string, Cookie> */
    private array $cookies = [];

    /**
     * @param array<string, Cookie|string> $cookies
     */
    public function __construct(array $cookies = [])
    {
        foreach ($cookies as $name => $value) {
            if ($value instanceof Cookie) {
                $this->set($value);
            } else {
                $this->set(new Cookie((string)$name, (string)$value));
            }
        }
    }

    public static function fromHeader(string $cookieHeader): self
    {
        $jar = new self();
        if (trim($cookieHeader) === '') {
            return $jar;
        }

        $pairs = explode(';', $cookieHeader);
        foreach ($pairs as $pair) {
            $parts = explode('=', trim($pair), 2);
            if (count($parts) === 2) {
                $name = rawurldecode(trim($parts[0]));
                $value = rawurldecode(trim($parts[1]));
                $jar->set(new Cookie($name, $value));
            }
        }

        return $jar;
    }

    public function set(Cookie $cookie): self
    {
        $this->cookies[$cookie->getName()] = $cookie;
        return $this;
    }

    public function get(string $name): ?Cookie
    {
        return $this->cookies[$name] ?? null;
    }

    public function getValue(string $name, ?string $default = null): ?string
    {
        return isset($this->cookies[$name]) ? $this->cookies[$name]->getValue() : $default;
    }

    public function has(string $name): bool
    {
        return isset($this->cookies[$name]);
    }

    public function delete(string $name, string $path = '/', ?string $domain = null): self
    {
        // Expire cookie
        $this->cookies[$name] = new Cookie($name, '', time() - 3600, $path, $domain);
        return $this;
    }

    /**
     * @return array<string, Cookie>
     */
    public function all(): array
    {
        return $this->cookies;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->cookies);
    }

    public function count(): int
    {
        return count($this->cookies);
    }
}
