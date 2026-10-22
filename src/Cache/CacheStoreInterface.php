<?php

declare(strict_types=1);

namespace Next\Cache;

interface CacheStoreInterface
{
    public function get(string $key): ?string;

    /**
     * @param list<string> $tags
     */
    public function set(string $key, string $data, int $ttl = 0, array $tags = []): void;

    public function has(string $key): bool;

    public function isStale(string $key): bool;

    public function delete(string $key): bool;

    public function revalidateTag(string $tag): void;

    public function revalidatePath(string $path): void;

    public function clear(): void;
}
