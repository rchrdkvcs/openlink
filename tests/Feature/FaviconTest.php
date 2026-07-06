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

    public function test_private_favicon_targets_are_rejected(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create())
            ->get(route('favicons.show', ['url' => 'http://127.0.0.1/admin']))
            ->assertNotFound();

        Http::assertNothingSent();
    }
}
