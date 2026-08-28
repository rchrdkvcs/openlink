<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FaviconTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_destination_favicon_is_served_from_the_application_origin(): void
    {
        Http::fake([
            'https://93.184.216.34/favicon.ico' => Http::response('icon-binary', 200, [
                'Content-Type' => 'image/x-icon',
            ]),
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('favicons.show', ['url' => 'https://93.184.216.34/some/page']))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/x-icon')
            ->assertSee('icon-binary');
    }

    public function test_favicon_is_discovered_from_the_destination_page_when_default_icon_is_html(): void
    {
        Http::fake([
            'https://93.184.216.34/favicon.ico' => Http::response('<html></html>', 200, [
                'Content-Type' => 'text/html; charset=utf-8',
            ]),
            'https://93.184.216.34/invite/abc' => Http::response(
                '<html><head><link rel="icon" href="/assets/favicon.png"></head></html>',
                200,
                ['Content-Type' => 'text/html; charset=utf-8'],
            ),
            'https://93.184.216.34/assets/favicon.png' => Http::response('png-binary', 200, [
                'Content-Type' => 'image/png',
            ]),
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('favicons.show', ['url' => 'https://93.184.216.34/invite/abc']))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertSee('png-binary');
    }

    public function test_relative_discovered_icons_are_resolved_against_the_final_redirected_url(): void
    {
        Http::fake([
            'https://93.184.216.34/favicon.ico' => Http::response('<html></html>', 200, [
                'Content-Type' => 'text/html; charset=utf-8',
            ]),
            'https://93.184.216.34/invite/abc' => Http::response('', 301, [
                'Location' => 'https://93.184.216.35/invite/abc',
            ]),
            'https://93.184.216.35/invite/abc' => Http::response(
                '<html><head><link href="/assets/favicon.ico" rel="icon"></head></html>',
                200,
                ['Content-Type' => 'text/html; charset=utf-8'],
            ),
            'https://93.184.216.35/assets/favicon.ico' => Http::response('redirected-icon', 200, [
                'Content-Type' => 'image/x-icon',
            ]),
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('favicons.show', ['url' => 'https://93.184.216.34/invite/abc']))
            ->assertOk()
            ->assertSee('redirected-icon');
    }

    public function test_favicon_can_be_discovered_from_a_web_app_manifest(): void
    {
        Http::fake([
            'https://93.184.216.34/favicon.ico' => Http::response('missing', 404),
            'https://93.184.216.34/app' => Http::response(
                '<html><head><link rel="manifest" href="/site.webmanifest"></head></html>',
                200,
                ['Content-Type' => 'text/html; charset=utf-8'],
            ),
            'https://93.184.216.34/site.webmanifest' => Http::response([
                'icons' => [
                    ['src' => '/icon-64.png', 'sizes' => '64x64', 'type' => 'image/png'],
                    ['src' => '/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
                ],
            ], 200, ['Content-Type' => 'application/manifest+json']),
            'https://93.184.216.34/icon-512.png' => Http::response('manifest-icon', 200, [
                'Content-Type' => 'image/png',
            ]),
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('favicons.show', ['url' => 'https://93.184.216.34/app']))
            ->assertOk()
            ->assertSee('manifest-icon');
    }

    public function test_standard_icon_paths_are_used_when_page_discovery_fails(): void
    {
        Http::fake([
            'https://93.184.216.34/favicon.ico' => Http::response('missing', 404),
            'https://93.184.216.34/app' => Http::response('<html><head></head></html>', 200, [
                'Content-Type' => 'text/html; charset=utf-8',
            ]),
            'https://93.184.216.34/favicon.png' => Http::response('missing', 404),
            'https://93.184.216.34/favicon.svg' => Http::response('missing', 404),
            'https://93.184.216.34/apple-touch-icon.png' => Http::response('apple-icon', 200, [
                'Content-Type' => 'image/png',
            ]),
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('favicons.show', ['url' => 'https://93.184.216.34/app']))
            ->assertOk()
            ->assertSee('apple-icon');
    }

    public function test_private_favicon_targets_are_rejected(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create())
            ->get(route('favicons.show', ['url' => 'http://127.0.0.1/admin']))
            ->assertNotFound();

        Http::assertNothingSent();
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
