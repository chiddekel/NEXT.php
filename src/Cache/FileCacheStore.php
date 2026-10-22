<?php

declare(strict_types=1);

namespace Next\Cache;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * File-system based cache for SSG and ISR.
 */
class FileCacheStore implements CacheStoreInterface
{
    private string $cacheDir;

    public function __construct(string $cacheDir)
    {
        $this->cacheDir = rtrim(str_replace('\\', '/', $cacheDir), '/');
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }
    }

    public function get(string $key): ?string
    {
        $entry = $this->readEntry($key);
        return $entry['data'] ?? null;
    }

    public function set(string $key, string $data, int $ttl = 0, array $tags = []): void
    {
        $filePath = $this->getFilePath($key);
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $expiresAt = ($ttl !== 0) ? time() + $ttl : null;
        $entry = [
            'key' => $key,
            'data' => $data,
            'expiresAt' => $expiresAt,
            'tags' => $tags,
            'createdAt' => time(),
        ];

        file_put_contents($filePath, serialize($entry), LOCK_EX);

        // Update tag index
        foreach ($tags as $tag) {
            $tagFile = $this->cacheDir . '/tags/' . sha1($tag) . '.idx';
            $tagDir = dirname($tagFile);
            if (!is_dir($tagDir)) {
                mkdir($tagDir, 0777, true);
            }
            file_put_contents($tagFile, $key . "\n", FILE_APPEND | LOCK_EX);
        }
    }

    public function has(string $key): bool
    {
        return file_exists($this->getFilePath($key));
    }

    public function isStale(string $key): bool
    {
        $entry = $this->readEntry($key);
        if ($entry === null) {
            return true;
        }

        $expiresAt = $entry['expiresAt'];
        if ($expiresAt === null) {
            return false;
        }

        return time() >= $expiresAt;
    }

    public function delete(string $key): bool
    {
        $file = $this->getFilePath($key);
        if (file_exists($file)) {
            return unlink($file);
        }
        return false;
    }

    public function revalidateTag(string $tag): void
    {
        $tagFile = $this->cacheDir . '/tags/' . sha1($tag) . '.idx';
        if (!file_exists($tagFile)) {
            return;
        }

        $lines = file($tagFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $key) {
            $this->delete($key);
        }

        unlink($tagFile);
    }

    public function revalidatePath(string $path): void
    {
        $this->delete('route:' . $path);
    }

    public function clear(): void
    {
        if (!is_dir($this->cacheDir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->cacheDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
    }

    private function getFilePath(string $key): string
    {
        return $this->cacheDir . '/entries/' . sha1($key) . '.cache';
    }

    /**
     * @return array{data: string, expiresAt: ?int, tags: list<string>}|null
     */
    private function readEntry(string $key): ?array
    {
        $filePath = $this->getFilePath($key);
        if (!file_exists($filePath)) {
            return null;
        }

        $contents = file_get_contents($filePath);
        if ($contents === false) {
            return null;
        }

        $unserialized = @unserialize($contents);
        if (!is_array($unserialized) || !isset($unserialized['data'])) {
            return null;
        }

        return $unserialized;
    }
}
