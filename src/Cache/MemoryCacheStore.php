<?php

declare(strict_types=1);

namespace Next\Cache;

/**
 * In-memory cache implementation, perfect for tests and ephemeral runs.
 */
class MemoryCacheStore implements CacheStoreInterface
{
    /** @var array<string, array{data: string, expiresAt: ?int, tags: list<string>}> */
    private array $storage = [];

    /** @var array<string, list<string>> tag => list of keys */
    private array $tags = [];

    public function get(string $key): ?string
    {
        if (!isset($this->storage[$key])) {
            return null;
        }

        return $this->storage[$key]['data'];
    }

    public function set(string $key, string $data, int $ttl = 0, array $tags = []): void
    {
        $expiresAt = ($ttl !== 0) ? time() + $ttl : null;
        $this->storage[$key] = [
            'data' => $data,
            'expiresAt' => $expiresAt,
            'tags' => $tags,
        ];

        foreach ($tags as $tag) {
            $this->tags[$tag][] = $key;
        }
    }

    public function has(string $key): bool
    {
        return isset($this->storage[$key]);
    }

    public function isStale(string $key): bool
    {
        if (!isset($this->storage[$key])) {
            return true;
        }

        $expiresAt = $this->storage[$key]['expiresAt'];
        if ($expiresAt === null) {
            return false;
        }

        return time() >= $expiresAt;
    }

    public function delete(string $key): bool
    {
        if (isset($this->storage[$key])) {
            unset($this->storage[$key]);
            return true;
        }
        return false;
    }

    public function revalidateTag(string $tag): void
    {
        if (isset($this->tags[$tag])) {
            foreach ($this->tags[$tag] as $key) {
                unset($this->storage[$key]);
            }
            unset($this->tags[$tag]);
        }
    }

    public function revalidatePath(string $path): void
    {
        $this->delete('route:' . $path);
    }

    public function clear(): void
    {
        $this->storage = [];
        $this->tags = [];
    }
}
