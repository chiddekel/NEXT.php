<?php

declare(strict_types=1);

namespace Next\Tests;

use Next\Cache\FileCacheStore;
use Next\Cache\MemoryCacheStore;
use PHPUnit\Framework\TestCase;

class CacheTest extends TestCase
{
    private string $tempCacheDir;

    protected function setUp(): void
    {
        $this->tempCacheDir = sys_get_temp_dir() . '/next_cache_test_' . uniqid();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempCacheDir)) {
            $store = new FileCacheStore($this->tempCacheDir);
            $store->clear();
            rmdir($this->tempCacheDir);
        }
    }

    public function testMemoryCacheStoreTtlAndTags(): void
    {
        $cache = new MemoryCacheStore();

        $cache->set('post:1', 'Post 1 content', 1, ['posts']);
        $cache->set('post:2', 'Post 2 content', 60, ['posts']);
        $cache->set('user:1', 'User 1 content', 60, ['users']);

        $this->assertTrue($cache->has('post:1'));
        $this->assertSame('Post 1 content', $cache->get('post:1'));
        $this->assertFalse($cache->isStale('post:1'));

        // Tag revalidation
        $cache->revalidateTag('posts');
        $this->assertFalse($cache->has('post:1'));
        $this->assertFalse($cache->has('post:2'));
        $this->assertTrue($cache->has('user:1'));
    }

    public function testFileCacheStorePersistence(): void
    {
        $cache = new FileCacheStore($this->tempCacheDir);

        $cache->set('page:home', '<html><body>Home</body></html>', 3600, ['pages']);
        $this->assertTrue($cache->has('page:home'));
        $this->assertSame('<html><body>Home</body></html>', $cache->get('page:home'));
        $this->assertFalse($cache->isStale('page:home'));

        $cache->revalidatePath('/home');
        // Path is route:/home
        $cache->set('route:/home', 'cached-route', 3600);
        $this->assertTrue($cache->has('route:/home'));
        $cache->revalidatePath('/home');
        $this->assertFalse($cache->has('route:/home'));

        $cache->clear();
        $this->assertFalse($cache->has('page:home'));
    }
}
