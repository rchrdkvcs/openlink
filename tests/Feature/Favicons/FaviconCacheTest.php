<?php

namespace Tests\Feature\Favicons;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FaviconCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_favicon_resolution_is_shared_by_urls_on_the_same_origin(): void
    {
        Http::fake([
            'https://93.184.216.34/favicon.ico' => Http::response('cached-icon', 200, [
                'Content-Type' => 'image/png',
            ]),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('favicons.show', ['url' => 'https://93.184.216.34/first']))
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=604800, private')
            ->assertSee('cached-icon');

        $this->actingAs($user)
            ->get(route('favicons.show', ['url' => 'https://93.184.216.34/another/path']))
            ->assertOk()
            ->assertSee('cached-icon');

        Http::assertSentCount(1);

        $this->travel(8)->days();

        $this->actingAs($user)
            ->get(route('favicons.show', ['url' => 'https://93.184.216.34/after-expiry']))
            ->assertOk();

        Http::assertSentCount(2);
    }

    public function test_failed_resolution_is_cached_for_one_hour(): void
    {
        Http::fake(Http::response('missing', 404));

        $user = User::factory()->create();
        $url = 'https://93.184.216.34/page';

        $this->actingAs($user)
            ->get(route('favicons.show', ['url' => $url]))
            ->assertNotFound()
            ->assertHeader('Cache-Control', 'max-age=3600, private');

        $requestCount = count(Http::recorded());

        $this->actingAs($user)
            ->get(route('favicons.show', ['url' => $url]))
            ->assertNotFound();

        $this->assertCount($requestCount, Http::recorded());

        $this->travel(61)->minutes();

        $this->actingAs($user)
            ->get(route('favicons.show', ['url' => $url]))
            ->assertNotFound();

        $this->assertGreaterThan($requestCount, count(Http::recorded()));
    }

    public function test_explicit_ports_have_distinct_favicon_cache_entries(): void
    {
        Http::fake([
            'https://93.184.216.34:8443/favicon.ico' => Http::response('port-icon', 200, [
                'Content-Type' => 'image/png',
            ]),
            'https://93.184.216.34/favicon.ico' => Http::response('default-icon', 200, [
                'Content-Type' => 'image/png',
            ]),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('favicons.show', ['url' => 'https://93.184.216.34:8443/page']))
            ->assertOk()
            ->assertSee('port-icon');

        $this->actingAs($user)
            ->get(route('favicons.show', ['url' => 'https://93.184.216.34/page']))
            ->assertOk()
            ->assertSee('default-icon');

        Http::assertSentCount(2);
    }
}
