<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InstanceUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        foreach (['update-request', 'update-heartbeat', 'update-status'] as $file) {
            @unlink(storage_path('app/'.$file));
        }

        parent::tearDown();
    }

    public function test_only_an_instance_admin_can_request_an_available_update_with_a_running_updater(): void
    {
        config()->set('openlink.version', 'v1.2.4');
        config()->set('openlink.image_tag', 'latest');
        config()->set('openlink.updater_enabled', true);
        Cache::forget('openlink.latest-release');
        Http::fake([
            'api.github.com/repos/rchrdkvcs/openlink/releases/latest' => Http::response([
                'tag_name' => 'v1.2.5',
                'html_url' => 'https://github.com/rchrdkvcs/openlink/releases/tag/v1.2.5',
            ]),
        ]);

        touch(storage_path('app/update-heartbeat'));

        $this->actingAs(User::factory()->create())
            ->withHeader('Host', 'localhost')
            ->post(route('instance-update.store'))
            ->assertForbidden();

        $admin = User::factory()->create(['is_instance_admin' => true]);
        $this->actingAs($admin)
            ->withHeader('Host', 'localhost')
            ->post(route('instance-update.store'))
            ->assertRedirect();

        $this->assertFileExists(storage_path('app/update-request'));

        $this->actingAs($admin)
            ->withHeader('Host', 'localhost')
            ->post(route('instance-update.store'))
            ->assertStatus(409);
    }

    public function test_update_request_is_rejected_when_updater_is_not_running(): void
    {
        config()->set('openlink.updater_enabled', true);

        $this->actingAs(User::factory()->create(['is_instance_admin' => true]))
            ->withHeader('Host', 'localhost')
            ->post(route('instance-update.store'))
            ->assertForbidden();
    }
}
