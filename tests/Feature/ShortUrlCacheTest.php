<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\ShortLink;
use App\Models\User;
use App\Models\Workspace;
use App\Services\ShortLinks\ShortUrlCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ShortUrlCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_is_cached_under_the_short_url_key(): void
    {
        [$domain, $link] = $this->linkOn('cache.test', 'launch');
        $cache = app(ShortUrlCache::class);

        $this->assertSame($link->id, $cache->shortLinkId($domain, 'launch'));
        $this->assertSame($link->id, Cache::get($cache->key($domain, 'launch')));
        $this->assertNull($cache->shortLinkId($domain, 'missing'));
    }

    public function test_slug_change_forgets_previous_and_new_short_url(): void
    {
        [$domain, $link] = $this->linkOn('cache.test', 'launch');
        $cache = app(ShortUrlCache::class);
        $cache->shortLinkId($domain, 'launch');
        Cache::put($cache->key($domain, 'renamed'), 999, 600);

        $link->update(['slug' => 'renamed']);

        $this->assertFalse(Cache::has($cache->key($domain, 'launch')));
        $this->assertFalse(Cache::has($cache->key($domain, 'renamed')));
    }

    public function test_domain_change_forgets_short_url_on_both_domains(): void
    {
        [$domain, $link] = $this->linkOn('cache.test', 'launch');
        [$otherDomain] = $this->linkOn('other.test', 'unrelated');
        $cache = app(ShortUrlCache::class);
        $cache->shortLinkId($domain, 'launch');
        Cache::put($cache->key($otherDomain, 'launch'), 999, 600);

        $link->update(['domain_id' => $otherDomain->id]);

        $this->assertFalse(Cache::has($cache->key($domain, 'launch')));
        $this->assertFalse(Cache::has($cache->key($otherDomain, 'launch')));
    }

    public function test_previous_address_is_forgotten_even_after_the_save_completed(): void
    {
        [$domain, $link] = $this->linkOn('cache.test', 'launch');
        $link->slug = 'renamed';
        $link->saveQuietly();
        $cache = app(ShortUrlCache::class);
        Cache::put($cache->key($domain, 'launch'), $link->id, 600);

        $cache->forgetForShortLink($link);

        $this->assertFalse(Cache::has($cache->key($domain, 'launch')));
    }

    public function test_unrelated_update_keeps_other_short_urls_cached(): void
    {
        [$domain, $link] = $this->linkOn('cache.test', 'launch');
        $cache = app(ShortUrlCache::class);
        Cache::put($cache->key($domain, 'neighbour'), 42, 600);

        $link->update(['destination_url' => 'https://example.com/changed']);

        $this->assertSame(42, Cache::get($cache->key($domain, 'neighbour')));
    }

    public function test_deletion_forgets_short_url(): void
    {
        [$domain, $link] = $this->linkOn('cache.test', 'launch');
        $cache = app(ShortUrlCache::class);
        $cache->shortLinkId($domain, 'launch');

        $link->delete();

        $this->assertFalse(Cache::has($cache->key($domain, 'launch')));
    }

    private function linkOn(string $hostname, string $slug): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $user->id, 'name' => $hostname, 'slug' => str()->slug($hostname), 'settings' => []]);
        $domain = Domain::create([
            'workspace_id' => $workspace->id,
            'hostname' => $hostname,
            'status' => Domain::STATUS_ACTIVE,
            'verification_token' => 'test-token-'.str()->random(12),
            'verified_at' => now(),
        ]);
        $link = ShortLink::create([
            'workspace_id' => $workspace->id,
            'domain_id' => $domain->id,
            'slug' => $slug,
            'destination_url' => 'https://example.com/'.$slug,
        ]);

        return [$domain, $link];
    }
}
