<?php

namespace Tests\Unit;

use App\Support\SharedCache;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SharedCacheTest extends TestCase
{
    public function test_builds_once_serves_stale_while_another_refreshes(): void
    {
        $n = 0;
        $make = function () use (&$n) { return ++$n; };
        $this->assertSame(1, SharedCache::remember('k', 60, $make));
        $this->assertSame(1, SharedCache::remember('k', 60, $make));          // fresh: not rebuilt
        Cache::put('k', ['v' => 1, 't' => time() - 120], 600);                // stale
        $lock = Cache::lock('k:lock', 120); $this->assertTrue($lock->get());  // another request is refreshing
        $this->assertSame(1, SharedCache::remember('k', 60, $make));          // previous value meanwhile
        $lock->release();
        $this->assertSame(2, SharedCache::remember('k', 60, $make));          // now this request refreshes
        $this->assertSame(2, SharedCache::remember('k', 60, $make));
    }
}
