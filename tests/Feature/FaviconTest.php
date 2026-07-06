<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FaviconTest extends TestCase
{
    use RefreshDatabase;

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
}
