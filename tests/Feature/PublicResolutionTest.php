<?php

namespace Tests\Feature;

use App\Actions\Domains\DomainLifecycle;
use App\Actions\Resolution\PublicResolution;
use App\Actions\Resolution\ResolutionEntry;
use App\Actions\Resolution\ResolutionResult;
use App\Actions\Resolution\ResolutionView;
use App\Models\AnalyticsEvent;
use App\Models\Domain;
use App\Models\QrCode;
use App\Models\ShortLink;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Analytics\Outcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PublicResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['openlink.analytics.via_queue' => true]);
    }

    public function test_visit_limit_rechecks_count_when_two_resolutions_start_with_stale_link_instances(): void
    {
        $link = $this->link('one-visit', ['visit_limit' => 1, 'password_hash' => Hash::make('secret')]);
        $firstSnapshot = ShortLink::findOrFail($link->id);
        $secondSnapshot = ShortLink::findOrFail($link->id);

        $first = $this->resolve(ResolutionEntry::passwordSubmission($firstSnapshot, 'secret'));
        $second = $this->resolve(ResolutionEntry::passwordSubmission($secondSnapshot, 'secret'));

        $this->assertSame(Outcome::SUCCESS, $first->outcome);
        $this->assertSame('https://example.com/one-visit', $first->redirectUrl);
        $this->assertSame(Outcome::VISIT_LIMIT_REACHED, $second->outcome);
        $this->assertSame(1, $link->fresh()->successful_visits);
    }

    public function test_visit_limit_added_after_link_load_is_checked_when_reserving_visit(): void
    {
        $link = $this->link('new-limit', ['successful_visits' => 1, 'password_hash' => Hash::make('secret')]);
        $snapshotWithoutLimit = ShortLink::findOrFail($link->id);
        $link->update(['visit_limit' => 1]);

        $result = $this->resolve(ResolutionEntry::passwordSubmission($snapshotWithoutLimit, 'secret'));

        $this->assertSame(Outcome::VISIT_LIMIT_REACHED, $result->outcome);
        $this->assertSame(1, $link->fresh()->successful_visits);
    }

    public function test_password_submission_ignores_qr_code_of_another_short_link(): void
    {
        $link = $this->link('secret', ['password_hash' => Hash::make('opensesame')]);
        $otherLink = $this->link('other');
        $foreignQrCode = QrCode::create(['short_link_id' => $otherLink->id, 'name' => 'Foreign', 'token' => 'foreign']);

        $this->post(route('public.password', $link), ['password' => 'wrong', 'qr_code_id' => $foreignQrCode->id])
            ->assertStatus(403);
        $this->post(route('public.password', $link), ['password' => 'opensesame', 'qr_code_id' => $foreignQrCode->id])
            ->assertRedirect('https://example.com/secret');

        $this->assertSame(2, AnalyticsEvent::query()->where('short_link_id', $link->id)->count());
        $this->assertSame(0, AnalyticsEvent::query()->whereNotNull('qr_code_id')->count());
        $this->assertSame(0, AnalyticsEvent::query()->where('metric', 'scan')->count());
    }

    public function test_password_submission_attributes_scan_to_qr_code_of_the_short_link(): void
    {
        $link = $this->link('secret', ['password_hash' => Hash::make('opensesame')]);
        $qrCode = QrCode::create(['short_link_id' => $link->id, 'name' => 'Poster', 'token' => 'poster']);

        $this->post(route('public.password', $link), ['password' => 'wrong', 'qr_code_id' => $qrCode->id])
            ->assertStatus(403)
            ->assertInertia(fn ($page) => $page->component('Public/Password')->where('qrCodeId', $qrCode->id));
        $this->post(route('public.password', $link), ['password' => 'opensesame', 'qr_code_id' => $qrCode->id])
            ->assertRedirect('https://example.com/secret');

        $this->assertDatabaseHas('analytics_events', ['outcome' => Outcome::PASSWORD_FAILED, 'metric' => 'scan', 'qr_code_id' => $qrCode->id]);
        $this->assertDatabaseHas('analytics_events', ['outcome' => Outcome::SUCCESS, 'metric' => 'scan', 'qr_code_id' => $qrCode->id]);
    }

    public function test_password_prompt_is_recorded_as_its_own_outcome(): void
    {
        $link = $this->link('secret', ['password_hash' => Hash::make('opensesame')]);

        $result = $this->resolve(ResolutionEntry::shortUrl('/secret/'));

        $this->assertSame(Outcome::PASSWORD_REQUIRED, $result->outcome);
        $this->assertSame(ResolutionView::PasswordForm, $result->view);
        $this->assertTrue($result->shortLink->is($link));
        $this->assertSame([Outcome::PASSWORD_REQUIRED], AnalyticsEvent::query()->pluck('outcome')->all());
        $this->assertNotContains(Outcome::PASSWORD_REQUIRED, Outcome::blocked());
    }

    public function test_domain_activation_is_checked_once_per_resolution(): void
    {
        $this->link('launch');
        $this->mock(DomainLifecycle::class)->shouldReceive('activateOnObservedTraffic')->once();

        $result = $this->resolve(ResolutionEntry::shortUrl('launch'));

        $this->assertSame(Outcome::SUCCESS, $result->outcome);
    }

    public function test_scheduled_link_shows_scheduled_view_even_with_fallback_url(): void
    {
        $this->link('soon', ['activates_at' => now()->addDay(), 'fallback_url' => 'https://example.com/fallback']);

        $result = $this->resolve(ResolutionEntry::shortUrl('soon'));

        $this->assertSame(ResolutionView::Scheduled, $result->view);
        $this->assertDatabaseHas('analytics_events', ['outcome' => Outcome::SCHEDULED, 'metric' => 'visit']);
    }

    public function test_unknown_slug_on_unknown_host_is_unavailable(): void
    {
        $result = app(PublicResolution::class)->resolve(
            Request::create('http://nowhere.test/missing'),
            ResolutionEntry::shortUrl('missing'),
        );

        $this->assertSame(Outcome::DOMAIN_UNAVAILABLE, $result->outcome);
        $this->assertSame(ResolutionView::Unavailable, $result->view);
    }

    private function resolve(ResolutionEntry $entry): ResolutionResult
    {
        $request = Request::create('http://localhost/');
        $request->setLaravelSession($this->app['session.store']);

        return app(PublicResolution::class)->resolve($request, $entry);
    }

    private function link(string $slug, array $attributes = []): ShortLink
    {
        $domain = Domain::query()->where('hostname', 'localhost')->first() ?? $this->domain();

        return ShortLink::create([
            'workspace_id' => $domain->workspace_id,
            'domain_id' => $domain->id,
            'slug' => $slug,
            'destination_url' => 'https://example.com/'.$slug,
            ...$attributes,
        ]);
    }

    private function domain(): Domain
    {
        $user = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $user->id, 'name' => 'Events', 'slug' => 'events', 'settings' => []]);
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => WorkspaceMember::ROLE_OWNER]);

        return Domain::create([
            'workspace_id' => $workspace->id,
            'hostname' => 'localhost',
            'status' => Domain::STATUS_ACTIVE,
            'verification_token' => 'test-token-'.str()->random(12),
            'verified_at' => now(),
        ]);
    }
}
