<?php

namespace Tests\Feature\Services\Shared\Cache;

use App\Services\Shared\Cache\CacheTagsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CacheTagsManagerTest extends TestCase
{
    use RefreshDatabase;

    private CacheTagsManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'database']);
        $this->manager = $this->app->make(CacheTagsManager::class);
    }

    #[Test]
    public function key_joins_segments_with_colon(): void
    {
        $this->assertSame('vehicle:42:fiscal', $this->manager->key('vehicle', 42, 'fiscal'));
        $this->assertSame('alpha', $this->manager->key('alpha'));
        $this->assertSame('a:b:c:d', $this->manager->key('a', 'b', 'c', 'd'));
    }

    #[Test]
    public function key_casts_integer_segments_to_string(): void
    {
        $this->assertSame('vehicle:42:2024', $this->manager->key('vehicle', 42, 2024));
    }

    #[Test]
    public function key_throws_when_no_segment_provided(): void
    {
        $this->expectException(LogicException::class);
        $this->manager->key();
    }

    #[Test]
    public function invalidate_by_prefix_removes_exact_key(): void
    {
        Cache::put('vehicle:42', 'summary', 3600);
        Cache::put('unrelated', 'keep', 3600);

        $removed = $this->manager->invalidateByPrefix('vehicle:42');

        $this->assertSame(1, $removed);
        $this->assertFalse(Cache::has('vehicle:42'));
        $this->assertTrue(Cache::has('unrelated'));
    }

    #[Test]
    public function invalidate_by_prefix_removes_all_descendants(): void
    {
        Cache::put('vehicle:42:fiscal', 'A', 3600);
        Cache::put('vehicle:42:assignments', 'B', 3600);
        Cache::put('vehicle:42:lcd:acme:2024', 'C', 3600);
        Cache::put('unrelated', 'keep', 3600);

        $removed = $this->manager->invalidateByPrefix('vehicle:42');

        $this->assertSame(3, $removed);
        $this->assertFalse(Cache::has('vehicle:42:fiscal'));
        $this->assertFalse(Cache::has('vehicle:42:assignments'));
        $this->assertFalse(Cache::has('vehicle:42:lcd:acme:2024'));
        $this->assertTrue(Cache::has('unrelated'));
    }

    #[Test]
    public function invalidate_by_prefix_removes_both_exact_and_descendants(): void
    {
        Cache::put('vehicle:42', 'summary', 3600);
        Cache::put('vehicle:42:fiscal', 'A', 3600);
        Cache::put('vehicle:42:assignments', 'B', 3600);

        $removed = $this->manager->invalidateByPrefix('vehicle:42');

        $this->assertSame(3, $removed);
    }

    #[Test]
    public function invalidate_by_prefix_does_not_touch_sibling_prefixes(): void
    {
        // Le bug historique qu'on veut éviter : `vehicle:42` et `vehicle:420`
        // partagent le préfixe `vehicle:42`. Sans la frontière `:`, un LIKE
        // naïf invaliderait les deux.
        Cache::put('vehicle:42:fiscal', 'A', 3600);
        Cache::put('vehicle:420:fiscal', 'keep', 3600);
        Cache::put('vehicle:421:fiscal', 'keep', 3600);

        $removed = $this->manager->invalidateByPrefix('vehicle:42');

        $this->assertSame(1, $removed);
        $this->assertTrue(Cache::has('vehicle:420:fiscal'));
        $this->assertTrue(Cache::has('vehicle:421:fiscal'));
    }

    #[Test]
    public function invalidate_by_prefix_normalizes_trailing_colon_in_argument(): void
    {
        Cache::put('vehicle:42:fiscal', 'A', 3600);

        // Argument avec `:` final — doit être toléré et normalisé.
        $removed = $this->manager->invalidateByPrefix('vehicle:42:');

        $this->assertSame(1, $removed);
    }

    #[Test]
    public function invalidate_by_prefix_returns_zero_when_nothing_matches(): void
    {
        Cache::put('other:key', 'keep', 3600);

        $removed = $this->manager->invalidateByPrefix('vehicle:99');

        $this->assertSame(0, $removed);
        $this->assertTrue(Cache::has('other:key'));
    }
}
