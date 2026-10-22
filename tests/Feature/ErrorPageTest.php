<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->post('/__error-page/{status}', fn (int $status) => abort($status));
        Route::middleware('api')->post('/api/__error-page/{status}', fn (int $status) => abort($status));
    }

    public function test_handled_statuses_render_the_error_page_with_their_status(): void
    {
        config(['app.debug' => false]);

        foreach ([403, 404, 419, 429, 500, 503] as $status) {
            $this->post("/__error-page/{$status}")
                ->assertStatus($status)
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->component('Error')
                    ->where('status', $status));
        }
    }

    public function test_inertia_visits_receive_the_error_page_as_an_inertia_response(): void
    {
        config(['app.debug' => false]);

        $this->post('/__error-page/403', [], ['X-Inertia' => 'true'])
            ->assertForbidden()
            ->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', 'Error')
            ->assertJsonPath('props.status', 403);
    }

    public function test_unhandled_statuses_keep_the_framework_response(): void
    {
        config(['app.debug' => false]);

        $this->post('/__error-page/405')
            ->assertStatus(405)
            ->assertHeaderMissing('X-Inertia');

        $this->post('/__error-page/405')->assertDontSee('"component":"Error"', false);
    }

    public function test_json_and_api_requests_skip_the_error_page(): void
    {
        config(['app.debug' => false]);

        $this->postJson('/__error-page/404')
            ->assertNotFound()
            ->assertDontSee('"component":"Error"', false);

        $this->post('/api/__error-page/404')
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }

    public function test_debug_mode_keeps_the_framework_exception_page(): void
    {
        config(['app.debug' => true]);

        $this->post('/__error-page/500')
            ->assertStatus(500)
            ->assertDontSee('"component":"Error"', false);
    }
}
