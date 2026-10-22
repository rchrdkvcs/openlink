<?php

namespace Tests\Feature\Conversions;

use App\Models\ConversionAttribution;
use App\Models\Domain;
use App\Models\RoutingRule;
use App\Models\ShortLink;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\ShortLinks\SmartRouting;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesWorkspaces;
use Tests\Support\UserAgents;
use Tests\TestCase;

class ConversionEndpointTest extends TestCase
{
    use CreatesWorkspaces;
    use RefreshDatabase;

    private Workspace $workspace;

    private Domain $domain;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-21 12:00:00'));
        config(['openlink.analytics.via_queue' => true]);
        $this->withHeader('User-Agent', UserAgents::SAFARI_MAC);

        [$this->workspace, $this->domain, $this->owner] = $this->workspaceWithActiveDomain();
    }

    public function test_a_valid_token_records_one_conversion_for_the_visit(): void
    {
        $link = $this->goalLink('signup');
        $token = $this->visit('signup');
        Sanctum::actingAs($this->owner);

        $this->convert($token)
            ->assertCreated()
            ->assertJsonPath('data.status', 'recorded')
            ->assertJsonPath('data.short_link_id', $link->id)
            ->assertJsonPath('data.conversion_goal', 'Registration');

        $this->assertNotNull(ConversionAttribution::query()->sole()->converted_at);
    }

    public function test_retries_and_replays_are_acknowledged_without_counting_twice(): void
    {
        $this->goalLink('signup');
        $token = $this->visit('signup');
        Sanctum::actingAs($this->owner);

        $this->convert($token)->assertCreated();
        $convertedAt = ConversionAttribution::query()->sole()->converted_at;
        $this->travel(5)->minutes();

        $this->convert($token)->assertOk()->assertJsonPath('data.status', 'duplicate');
        $this->convert($token)->assertOk()->assertJsonPath('data.status', 'duplicate');

        $this->assertSame(1, ConversionAttribution::query()->whereNotNull('converted_at')->count());
        $this->assertTrue($convertedAt->equalTo(ConversionAttribution::query()->sole()->converted_at));
    }

    public function test_tokens_expire_at_the_end_of_the_attribution_window(): void
    {
        $this->goalLink('signup');
        $insideWindow = $this->visit('signup');
        $outsideWindow = $this->visit('signup');
        Sanctum::actingAs($this->owner);

        $this->travel(24)->hours();
        $this->travel(-1)->seconds();
        $this->convert($insideWindow)->assertCreated();

        $this->travel(2)->seconds();
        $this->assertInvalidToken($this->convert($outsideWindow));

        $this->assertSame(1, ConversionAttribution::query()->whereNotNull('converted_at')->count());
    }

    public function test_forged_tokens_are_rejected(): void
    {
        $this->goalLink('signup');
        $this->visit('signup');
        Sanctum::actingAs($this->owner);

        $this->assertInvalidToken($this->convert(Str::random(48)));
        $this->assertInvalidToken($this->convert(ConversionAttribution::query()->sole()->token_hash));

        $this->assertSame(0, ConversionAttribution::query()->whereNotNull('converted_at')->count());
    }

    public function test_tokens_of_deleted_links_are_rejected(): void
    {
        $link = $this->goalLink('signup');
        $token = $this->visit('signup');
        Sanctum::actingAs($this->owner);

        $link->delete();

        $this->assertInvalidToken($this->convert($token));
        $this->assertSame(0, ConversionAttribution::query()->count());
    }

    public function test_tokens_of_links_whose_goal_was_removed_are_rejected(): void
    {
        $link = $this->goalLink('signup');
        $token = $this->visit('signup');
        Sanctum::actingAs($this->owner);

        $link->update(['conversion_goal' => null]);

        $this->assertInvalidToken($this->convert($token));
        $this->assertNull(ConversionAttribution::query()->sole()->converted_at);
    }

    public function test_tokens_are_rejected_in_another_workspace(): void
    {
        $this->goalLink('signup');
        $token = $this->visit('signup');
        $other = $this->workspaceFor($this->owner, 'Other');
        Sanctum::actingAs($this->owner);

        $this->assertInvalidToken($this->convert($token, $other));

        $this->assertNull(ConversionAttribution::query()->sole()->converted_at);
        $this->convert($token)->assertCreated();
    }

    public function test_only_members_who_can_edit_content_record_conversions(): void
    {
        $this->goalLink('signup');
        $token = $this->visit('signup');

        $this->postJson('/api/v1/conversions', ['token' => $token])->assertUnauthorized();

        Sanctum::actingAs($this->memberOf($this->workspace, WorkspaceMember::ROLE_VIEWER));
        $this->convert($token)->assertForbidden();

        Sanctum::actingAs(User::factory()->create());
        $this->convert($token)->assertForbidden();
        $this->assertNull(ConversionAttribution::query()->sole()->converted_at);

        Sanctum::actingAs($this->memberOf($this->workspace, WorkspaceMember::ROLE_EDITOR));
        $this->convert($token)->assertCreated();
    }

    public function test_requests_carrying_anything_but_the_token_are_rejected(): void
    {
        $this->goalLink('signup');
        $token = $this->visit('signup');
        Sanctum::actingAs($this->owner);

        $this->withHeaders(['X-Workspace-Id' => (string) $this->workspace->id])
            ->postJson('/api/v1/conversions', ['token' => $token, 'email' => 'visitor@example.com', 'value' => 49])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'value']);
        $this->withHeaders(['X-Workspace-Id' => (string) $this->workspace->id])
            ->postJson('/api/v1/conversions', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('token');

        $this->assertNull(ConversionAttribution::query()->sole()->converted_at);
    }

    public function test_the_endpoint_allows_120_requests_per_minute_per_user(): void
    {
        $editor = $this->memberOf($this->workspace, WorkspaceMember::ROLE_EDITOR);
        Sanctum::actingAs($this->owner);

        $this->convert('unknown')->assertUnprocessable()->assertHeader('X-RateLimit-Limit', '120');

        foreach (range(2, 120) as $attempt) {
            $this->convert('unknown')->assertUnprocessable();
        }

        $this->convert('unknown')->assertTooManyRequests();

        Sanctum::actingAs($editor);
        $this->convert('unknown')->assertUnprocessable();

        $this->travel(61)->seconds();
        Sanctum::actingAs($this->owner);
        $this->convert('unknown')->assertUnprocessable();
    }

    public function test_conversions_keep_the_routing_variant_of_the_original_visit(): void
    {
        $link = $this->goalLink('split');
        app(SmartRouting::class)->sync($link, [[
            'name' => 'Pricing test', 'type' => RoutingRule::TYPE_SPLIT_TEST, 'conditions' => [],
            'variants' => [
                ['name' => 'A', 'destination_url' => 'https://example.com/a', 'weight' => 50],
                ['name' => 'B', 'destination_url' => 'https://example.com/b', 'weight' => 50],
            ],
        ]]);
        $token = $this->visit('split');
        $attribution = ConversionAttribution::query()->sole();
        Sanctum::actingAs($this->owner);

        $this->assertNotNull($attribution->routing_variant_id);
        $this->convert($token)
            ->assertCreated()
            ->assertJsonPath('data.routing_rule_id', $attribution->routing_rule_id)
            ->assertJsonPath('data.routing_variant_id', $attribution->routing_variant_id);
    }

    private function goalLink(string $slug): ShortLink
    {
        return $this->shortLink($this->workspace, $this->domain, $slug, ['conversion_goal' => 'Registration']);
    }

    private function visit(string $slug): string
    {
        $location = $this->withHeaders(['Host' => 'localhost'])->get('/'.$slug)->assertRedirect()->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        return $query['openlink_attribution'];
    }

    private function convert(string $token, ?Workspace $workspace = null)
    {
        return $this->withHeaders(['X-Workspace-Id' => (string) ($workspace ?? $this->workspace)->id])
            ->postJson('/api/v1/conversions', ['token' => $token]);
    }

    private function assertInvalidToken($response): void
    {
        $response->assertUnprocessable()->assertJsonValidationErrors(['token' => 'The attribution token is invalid or has expired.']);
    }
}
