<?php

declare(strict_types=1);

namespace Next\Http;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Case-insensitive HTTP Headers collection.
 */
class Headers implements IteratorAggregate, Countable
{
    /** @var array<string, list<string>> normalized lower-case => list of values */
    private array $headers = [];

    /** @var array<string, string> normalized lower-case => original name */
    private array $originalNames = [];

    /**
     * @param array<string, string|list<string>> $headers
     */
    public function __construct(array $headers = [])
    {
        foreach ($headers as $name => $value) {
            $this->set($name, $value);
        }
    }

    /**
     * Set a header, replacing any previous value.
     *
     * @param string|list<string> $value
     */
    public function set(string $name, string|array $value): self
    {
        $normalized = strtolower($name);
        $this->originalNames[$normalized] = $name;

        if (is_array($value)) {
            $this->headers[$normalized] = array_values(array_map('strval', $value));
        } else {
            $this->headers[$normalized] = [(string) $value];
        }

        return $this;
    }

    /**
     * Append a value to a header.
     */
    public function append(string $name, string $value): self
    {
        $normalized = strtolower($name);
        if (!isset($this->headers[$normalized])) {
            return $this->set($name, $value);
        }

        $this->headers[$normalized][] = $value;
        return $this;
    }

    /**
     * Get the first value of a header or default.
     */
    public function get(string $name, ?string $default = null): ?string
    {
        $normalized = strtolower($name);
        if (!isset($this->headers[$normalized]) || empty($this->headers[$normalized])) {
            return $default;
        }

        return $this->headers[$normalized][0];
    }

    /**
     * Get all values for a header as an array.
     *
     * @return list<string>
     */
    public function getAll(string $name): array
    {
        $normalized = strtolower($name);
        return $this->headers[$normalized] ?? [];
    }

    /**
     * Check if a header exists.
     */
    public function has(string $name): bool
    {
        return isset($this->headers[strtolower($name)]);
    }

    /**
     * Remove a header.
     */
    public function delete(string $name): self
    {
        $normalized = strtolower($name);
        unset($this->headers[$normalized], $this->originalNames[$normalized]);
        return $this;
    }

    /**
     * Convert to array of header lines or key-values.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        $result = [];
        foreach ($this->headers as $normalized => $values) {
            $originalName = $this->originalNames[$normalized] ?? $normalized;
            $result[$originalName] = implode(', ', $values);
        }
        return $result;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->toArray());
    }

    public function count(): int
    {
        return count($this->headers);
    }
}
