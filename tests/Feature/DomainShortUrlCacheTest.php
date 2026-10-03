<?php

namespace Tests\Feature;

use App\Enums\DomainStatus;
use App\Models\ShortLink;
use App\Services\ShortLinks\ShortUrlCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\CreatesWorkspaceDomains;
use Tests\TestCase;

class DomainShortUrlCacheTest extends TestCase
{
    use CreatesWorkspaceDomains;
    use RefreshDatabase;

    public function test_dns_metadata_and_status_changes_preserve_short_url_cache_entries(): void
    {
        [$workspace, $domain] = $this->workspaceWithDomain();
        $link = $this->link($workspace->id, $domain->id);
        $key = app(ShortUrlCache::class)->key($domain, $link->slug);
        Cache::put($key, $link->id, 600);

        $domain->forceFill([
            'status' => DomainStatus::Active,
            'last_checked_at' => now(),
            'dns_pointed_at' => now(),
        ])->save();

        $this->assertSame($link->id, Cache::get($key));
    }

    public function test_hostname_change_invalidates_old_and_new_short_url_cache_entries(): void
    {
        [$workspace, $domain] = $this->workspaceWithDomain();
        $link = $this->link($workspace->id, $domain->id);
        $oldKey = app(ShortUrlCache::class)->key($domain, $link->slug);
        Cache::put($oldKey, $link->id, 600);
        Cache::put('resolution:new.example.test:launch', 999, 600);

        $domain->hostname = 'new.example.test';
        $domain->save();

        $this->assertFalse(Cache::has($oldKey));
        $this->assertFalse(Cache::has('resolution:new.example.test:launch'));
    }

    public function test_domain_deletion_invalidates_short_url_cache_entries(): void
    {
        [$workspace, $domain] = $this->workspaceWithDomain();
        $link = $this->link($workspace->id, $domain->id);
        $key = app(ShortUrlCache::class)->key($domain, $link->slug);
        Cache::put($key, $link->id, 600);

        $domain->delete();

        $this->assertFalse(Cache::has($key));
    }

    private function link(int $workspaceId, int $domainId): ShortLink
    {
        return ShortLink::create([
            'workspace_id' => $workspaceId,
            'domain_id' => $domainId,
            'slug' => 'launch',
            'destination_url' => 'https://example.com/launch',
        ]);
    }
}
