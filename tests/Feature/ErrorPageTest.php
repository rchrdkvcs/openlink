<?php

namespace Tests\Feature;

use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia;
use RuntimeException;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->post('/__error-page/{status}', fn (int $status) => abort($status));
        Route::middleware('api')->post('/api/__error-page/{status}', fn (int $status) => abort($status));
        Route::middleware('web')->post('/__error-page-throttled', fn () => abort(429, '', ['Retry-After' => '30']));
        Route::middleware('web')->post('/__error-page-retry-now', fn () => abort(503, '', ['Retry-After' => '0']));
        Route::middleware(['web', 'throttle:1,1'])->post('/__error-page-limited', fn () => 'ok');
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

    public function test_error_page_keeps_the_retry_after_header(): void
    {
        config(['app.debug' => false]);

        $this->post('/__error-page-throttled')
            ->assertTooManyRequests()
            ->assertHeader('Retry-After', '30')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Error')
                ->where('status', 429));
    }

    public function test_rate_limited_requests_keep_their_status_and_rate_limit_headers(): void
    {
        config(['app.debug' => false]);

        $this->post('/__error-page-limited')->assertOk();

        $response = $this->post('/__error-page-limited')
            ->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertHeader('X-RateLimit-Limit', '1')
            ->assertHeader('X-RateLimit-Remaining', '0')
            ->assertHeader('X-RateLimit-Reset')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Error')
                ->where('status', 429));

        $this->assertStringStartsWith('text/html', (string) $response->headers->get('Content-Type'));
    }

    public function test_a_zero_retry_after_value_is_kept(): void
    {
        config(['app.debug' => false]);

        $this->post('/__error-page-retry-now')
            ->assertServiceUnavailable()
            ->assertHeader('Retry-After', '0')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('status', 503));
    }

    public function test_maintenance_mode_keeps_refresh_and_retry_after_headers(): void
    {
        config(['app.debug' => false]);

        $this->app->instance(MaintenanceMode::class, new class implements MaintenanceMode
        {
            public function activate(array $payload): void {}

            public function deactivate(): void {}

            public function active(): bool
            {
                return true;
            }

            public function data(): array
            {
                return ['except' => [], 'redirect' => null, 'retry' => 60, 'refresh' => 15, 'secret' => null, 'status' => 503, 'template' => null];
            }
        });

        $this->get('/login')
            ->assertServiceUnavailable()
            ->assertHeader('Refresh', '15')
            ->assertHeader('Retry-After', '60')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Error')
                ->where('status', 503));
    }

    public function test_a_failing_error_page_falls_back_to_the_framework_response(): void
    {
        config(['app.debug' => false]);
        Inertia::share('unavailable', fn () => throw new RuntimeException('Shared data is unavailable.'));

        $this->post('/__error-page/500')
            ->assertStatus(500)
            ->assertDontSee('"component":"Error"', false);
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
