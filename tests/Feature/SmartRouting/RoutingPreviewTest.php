<?php

namespace Tests\Feature\SmartRouting;

use App\Enums\WorkspaceRole;
use App\Models\Domain;
use App\Models\RoutingRule;
use App\Models\ShortLink;
use App\Services\ResolutionContext;
use App\Services\ShortLinks\SmartRouting;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class RoutingPreviewTest extends TestCase
{
    use CreatesWorkspaces;
    use RefreshDatabase;

    private const WINDOW = ['type' => 'time_of_day', 'operator' => 'between', 'value' => ['from' => '09:00', 'to' => '18:00'], 'timezone' => 'Europe/Paris'];

    public function test_saved_routing_preview_selects_the_same_destination_as_public_resolution(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'smart', ['destination_url' => 'https://example.com/default']);
        $france = $this->conditionalRule($link, 'France', 1, [['type' => 'country', 'operator' => 'is', 'value' => 'FR']], 'https://example.com/fr');
        $this->conditionalRule($link, 'Mobile', 2, [['type' => 'device_type', 'operator' => 'is', 'value' => 'mobile']], 'https://example.com/mobile');

        $this->preview($owner, $workspace, $link, ['country' => 'FR', 'device_type' => 'mobile'])
            ->assertOk()
            ->assertJsonPath('source', 'saved')
            ->assertJsonPath('decision.rule_id', $france->id)
            ->assertJsonPath('decision.destination_url', 'https://example.com/fr')
            ->assertJsonPath('decision.is_default', false)
            ->assertJsonPath('rules.0.status', 'matched')
            ->assertJsonPath('rules.0.conditions.0.matched', true)
            ->assertJsonPath('rules.1.status', 'not_reached');

        $this->withHeaders(['Host' => 'localhost', 'CF-IPCountry' => 'FR'])
            ->get('/smart')
            ->assertRedirect('https://example.com/fr');
    }

    public function test_split_test_preview_and_production_pick_the_same_variant_for_the_same_seed(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'split');
        $rule = $this->splitRule($link, [
            ['name' => 'A', 'destination_url' => 'https://example.com/a', 'weight' => 30],
            ['name' => 'B', 'destination_url' => 'https://example.com/b', 'weight' => 70],
        ]);
        $routing = app(SmartRouting::class);
        $destinations = [];

        foreach (range(1, 12) as $visitor) {
            $response = $this->preview($owner, $workspace, $link, ['seed' => "visitor-$visitor"])->assertOk();
            $context = new ResolutionContext([], CarbonImmutable::now(), $response->json('context.visitor_hash'));
            $decision = $routing->resolve($link->fresh(), $context);

            $this->assertSame($decision->destinationUrl, $response->json('decision.destination_url'));
            $this->assertSame($decision->variant->id, $response->json('decision.variant_id'));
            $destinations[] = $decision->destinationUrl;
        }

        $this->assertSame(
            $this->preview($owner, $workspace, $link, ['seed' => 'visitor-1'])->json('context.visitor_hash'),
            $this->preview($owner, $workspace, $link, ['seed' => 'visitor-1'])->json('context.visitor_hash'),
        );
        $this->assertEqualsCanonicalizing(['https://example.com/a', 'https://example.com/b'], array_values(array_unique($destinations)));
        $this->preview($owner, $workspace, $link, ['seed' => 'visitor-1'])
            ->assertJsonPath('rules.0.total_weight', 100)
            ->assertJsonPath('rules.0.variants.0.share', 30)
            ->assertJsonPath('rules.0.variants.1.share', 70)
            ->assertJsonPath('decision.rule_id', $rule->id);
    }

    public function test_preview_never_records_visits_unlocks_activates_persists_or_requests_destinations(): void
    {
        Http::fake();
        [$workspace, , $owner] = $this->workspaceWithActiveDomain();
        $domain = Domain::create([
            'workspace_id' => $workspace->id,
            'hostname' => 'localhost.pending.test',
            'status' => Domain::STATUS_PENDING,
            'verification_token' => 'pending-token',
        ]);
        $link = $this->shortLink($workspace, $domain, 'locked', [
            'destination_url' => 'https://example.com/locked',
            'password_hash' => bcrypt('opensesame'),
            'visit_limit' => 1,
        ]);
        $this->conditionalRule($link, 'Everyone', 1, [], 'https://example.com/saved');

        $this->preview($owner, $workspace, $link, ['country' => 'FR'], [
            'destination_url' => 'https://example.com/draft-default',
            'routing_rules' => [['name' => 'Draft', 'destination_url' => 'https://example.com/draft', 'conditions' => []]],
        ])
            ->assertOk()
            ->assertJsonPath('decision.destination_url', 'https://example.com/draft')
            ->assertSessionMissing('openlink_protected_link_'.$link->id);

        $this->preview($owner, $workspace, $link)->assertOk()->assertJsonPath('decision.destination_url', 'https://example.com/saved');

        $link->refresh();
        $this->assertSame(0, $link->successful_visits);
        $this->assertSame('https://example.com/locked', $link->destination_url);
        $this->assertSame(['Everyone'], $link->routingRules()->pluck('name')->all());
        $this->assertDatabaseCount('analytics_events', 0);
        $this->assertSame(Domain::STATUS_PENDING, $domain->fresh()->status->value);
        Http::assertNothingSent();
    }

    public function test_preview_separates_simulated_destination_from_public_availability(): void
    {
        [$workspace, , $owner] = $this->workspaceWithActiveDomain();
        $domain = Domain::create([
            'workspace_id' => $workspace->id,
            'hostname' => 'pending.example.test',
            'status' => Domain::STATUS_PENDING,
            'verification_token' => 'pending-token',
        ]);
        $link = $this->shortLink($workspace, $domain, 'gated', [
            'is_enabled' => false,
            'password_hash' => bcrypt('opensesame'),
        ]);
        $this->conditionalRule($link, 'France', 1, [['type' => 'country', 'operator' => 'is', 'value' => 'FR']], 'https://example.com/fr');

        $this->preview($owner, $workspace, $link, ['country' => 'FR'])
            ->assertOk()
            ->assertJsonPath('decision.destination_url', 'https://example.com/fr')
            ->assertJsonPath('availability.status', 'disabled')
            ->assertJsonPath('availability.domain_usable', false)
            ->assertJsonPath('availability.password_protected', true)
            ->assertJsonPath('availability.available', false);
    }

    public function test_draft_preview_reports_rule_order_disabled_rules_and_condition_results(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'ordered');

        $this->preview($owner, $workspace, $link, ['country' => 'FR', 'device_type' => 'desktop'], [
            'destination_url' => 'https://example.com/default',
            'routing_rules' => [
                ['name' => 'Paused', 'is_enabled' => false, 'destination_url' => 'https://example.com/paused', 'conditions' => []],
                ['name' => 'Mobile France', 'destination_url' => 'https://example.com/mobile-fr', 'conditions' => [
                    ['type' => 'country', 'operator' => 'is', 'value' => 'FR'],
                    ['type' => 'device_type', 'operator' => 'is', 'value' => 'mobile'],
                ]],
                ['name' => 'France or mobile', 'match_mode' => 'any', 'destination_url' => 'https://example.com/either', 'conditions' => [
                    ['type' => 'country', 'operator' => 'is', 'value' => 'FR'],
                    ['type' => 'device_type', 'operator' => 'is', 'value' => 'mobile'],
                ]],
                ['name' => 'Everyone', 'destination_url' => 'https://example.com/everyone', 'conditions' => []],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('source', 'draft')
            ->assertJsonPath('rules.0.status', 'disabled')
            ->assertJsonPath('rules.1.status', 'not_matched')
            ->assertJsonPath('rules.1.conditions.0.matched', true)
            ->assertJsonPath('rules.1.conditions.1.matched', false)
            ->assertJsonPath('rules.2.status', 'matched')
            ->assertJsonPath('rules.3.status', 'not_reached')
            ->assertJsonPath('decision.rule_index', 2)
            ->assertJsonPath('decision.destination_url', 'https://example.com/either');
    }

    public function test_draft_preview_falls_back_to_the_default_destination_when_nothing_matches(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'fallback');

        $this->preview($owner, $workspace, $link, ['country' => 'DE'], [
            'destination_url' => 'https://example.com/draft-default',
            'routing_rules' => [
                ['name' => 'France', 'destination_url' => 'https://example.com/fr', 'conditions' => [['type' => 'country', 'operator' => 'is', 'value' => 'FR']]],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('rules.0.status', 'not_matched')
            ->assertJsonPath('decision.is_default', true)
            ->assertJsonPath('decision.rule_index', null)
            ->assertJsonPath('decision.destination_url', 'https://example.com/draft-default');
    }

    public function test_missing_context_only_matches_empty_conditions(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'unknown');

        $this->preview($owner, $workspace, $link, [], [
            'destination_url' => 'https://example.com/default',
            'routing_rules' => [
                ['name' => 'Not France', 'destination_url' => 'https://example.com/not-fr', 'conditions' => [['type' => 'country', 'operator' => 'is_not', 'value' => 'FR']]],
                ['name' => 'Unknown country', 'destination_url' => 'https://example.com/unknown', 'conditions' => [['type' => 'country', 'operator' => 'is_empty']]],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('rules.0.status', 'not_matched')
            ->assertJsonPath('rules.1.status', 'matched')
            ->assertJsonPath('context.dimensions.country', null)
            ->assertJsonPath('context.dimensions.referrer_channel', 'direct');
    }

    public function test_referrer_is_classified_like_a_public_visit(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'social');

        $this->preview($owner, $workspace, $link, ['referrer' => 'https://l.instagram.com/?u=x', 'utm_campaign' => ' launch '], [
            'destination_url' => 'https://example.com/default',
            'routing_rules' => [
                ['name' => 'Social launch', 'destination_url' => 'https://example.com/social', 'conditions' => [
                    ['type' => 'referrer_channel', 'operator' => 'is', 'value' => 'social'],
                    ['type' => 'utm_campaign', 'operator' => 'is', 'value' => 'launch'],
                ]],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('context.dimensions.referrer_host', 'l.instagram.com')
            ->assertJsonPath('context.dimensions.referrer_channel', 'social')
            ->assertJsonPath('decision.destination_url', 'https://example.com/social');
    }

    public function test_saved_variants_that_are_disabled_or_have_zero_weight_are_never_selected(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'weights');
        $this->splitRule($link, [
            ['name' => 'Paused', 'destination_url' => 'https://example.com/paused', 'weight' => 100, 'is_enabled' => false],
            ['name' => 'Empty', 'destination_url' => 'https://example.com/empty', 'weight' => 0],
            ['name' => 'Live', 'destination_url' => 'https://example.com/live', 'weight' => 10],
        ]);

        foreach (['a', 'b', 'c', 'd'] as $seed) {
            $this->preview($owner, $workspace, $link, ['seed' => $seed])
                ->assertOk()
                ->assertJsonPath('decision.destination_url', 'https://example.com/live')
                ->assertJsonPath('rules.0.total_weight', 10)
                ->assertJsonPath('rules.0.variants.0.eligible', false)
                ->assertJsonPath('rules.0.variants.0.share', 0)
                ->assertJsonPath('rules.0.variants.1.eligible', false)
                ->assertJsonPath('rules.0.variants.2.selected', true)
                ->assertJsonPath('rules.0.variants.2.share', 100);
        }
    }

    public function test_split_test_without_eligible_variants_is_skipped(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'skipped', ['destination_url' => 'https://example.com/default']);
        $this->splitRule($link, [
            ['name' => 'Paused', 'destination_url' => 'https://example.com/paused', 'weight' => 50, 'is_enabled' => false],
            ['name' => 'Empty', 'destination_url' => 'https://example.com/empty', 'weight' => 0],
        ]);

        $this->preview($owner, $workspace, $link)
            ->assertOk()
            ->assertJsonPath('rules.0.status', 'skipped')
            ->assertJsonPath('decision.is_default', true)
            ->assertJsonPath('decision.destination_url', 'https://example.com/default');
    }

    public function test_date_time_bounds_are_inclusive_for_between_and_strict_for_before_and_after(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'launch');
        $rules = [
            ['name' => 'After launch', 'destination_url' => 'https://example.com/after', 'conditions' => [
                ['type' => 'date_time', 'operator' => 'after', 'value' => '2026-06-01T09:00', 'timezone' => 'Europe/Paris'],
            ]],
            ['name' => 'Launch window', 'destination_url' => 'https://example.com/window', 'conditions' => [
                ['type' => 'date_time', 'operator' => 'between', 'value' => ['from' => '2026-06-01T09:00', 'to' => '2026-06-01T10:00'], 'timezone' => 'Europe/Paris'],
            ]],
        ];
        $draft = ['destination_url' => 'https://example.com/default', 'routing_rules' => $rules];

        $this->preview($owner, $workspace, $link, ['occurred_at' => '2026-06-01T07:00', 'timezone' => 'UTC'], $draft)
            ->assertOk()
            ->assertJsonPath('rules.0.status', 'not_matched')
            ->assertJsonPath('rules.1.status', 'matched');

        $this->preview($owner, $workspace, $link, ['occurred_at' => '2026-06-01T10:00', 'timezone' => 'Europe/Paris'], $draft)
            ->assertJsonPath('rules.0.status', 'matched');

        $this->preview($owner, $workspace, $link, ['occurred_at' => '2026-06-01T06:59', 'timezone' => 'UTC'], $draft)
            ->assertJsonPath('decision.is_default', true);
    }

    public function test_time_windows_follow_the_rule_timezone_across_daylight_saving_changes(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'office');
        $draft = ['destination_url' => 'https://example.com/default', 'routing_rules' => [
            ['name' => 'Office hours', 'destination_url' => 'https://example.com/office', 'conditions' => [self::WINDOW]],
        ]];

        $this->preview($owner, $workspace, $link, ['occurred_at' => '2026-03-28T07:30', 'timezone' => 'UTC'], $draft)
            ->assertOk()
            ->assertJsonPath('rules.0.status', 'not_matched');

        $this->preview($owner, $workspace, $link, ['occurred_at' => '2026-03-29T07:30', 'timezone' => 'UTC'], $draft)
            ->assertJsonPath('rules.0.status', 'matched');

        $this->preview($owner, $workspace, $link, ['occurred_at' => '2026-03-29T02:30', 'timezone' => 'Europe/Paris'], $draft)
            ->assertJsonPath('context.occurred_at', '2026-03-29T01:30:00+00:00')
            ->assertJsonPath('context.local_time', '2026-03-29T03:30:00+02:00');
    }

    public function test_viewer_may_preview_saved_routing_but_not_unsaved_drafts(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'viewer');
        $viewer = $this->memberOf($workspace, WorkspaceRole::Viewer->value);
        $editor = $this->memberOf($workspace, WorkspaceRole::Editor->value);
        $draft = ['destination_url' => 'https://example.com/draft', 'routing_rules' => []];

        $this->preview($viewer, $workspace, $link)->assertOk();
        $this->preview($viewer, $workspace, $link, [], $draft)->assertForbidden();
        $this->preview($editor, $workspace, $link, [], $draft)->assertOk()->assertJsonPath('decision.destination_url', 'https://example.com/draft');
    }

    public function test_links_from_another_workspace_cannot_be_previewed(): void
    {
        [$workspace, , $owner] = $this->workspaceWithActiveDomain();
        [$otherWorkspace, $otherDomain] = $this->workspaceWithActiveDomain('other.example.test');
        $foreign = $this->shortLink($otherWorkspace, $otherDomain, 'foreign');

        $this->preview($owner, $workspace, $foreign)->assertForbidden();
        $this->preview($owner, $workspace, $foreign, [], ['destination_url' => 'https://example.com/x', 'routing_rules' => []])->assertForbidden();
    }

    public function test_invalid_draft_conditions_and_context_are_rejected(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'invalid');
        $draft = fn (array $rule) => ['destination_url' => 'https://example.com/default', 'routing_rules' => [$rule]];

        $this->preview($owner, $workspace, $link, [], $draft(['destination_url' => 'https://example.com/x', 'conditions' => [['type' => 'planet', 'operator' => 'is', 'value' => 'mars']]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('routing_rules.0.conditions.0.type');

        $this->preview($owner, $workspace, $link, [], $draft(['conditions' => []]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('routing_rules.0.destination_url');

        $this->preview($owner, $workspace, $link, [], $draft(['destination_url' => 'https://localhost/invalid', 'conditions' => []]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('routing_rules.0.destination_url');

        $this->preview($owner, $workspace, $link, [], $draft(['type' => RoutingRule::TYPE_SPLIT_TEST, 'variants' => [
            ['destination_url' => 'https://example.com/a', 'weight' => 0],
            ['destination_url' => 'https://example.com/b', 'weight' => 50],
        ]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('routing_rules.0.variants.0.weight');

        $this->preview($owner, $workspace, $link, ['timezone' => 'Mars/Olympus', 'occurred_at' => 'tomorrow', 'country' => 'France'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['context.timezone', 'context.occurred_at', 'context.country']);

        $this->assertSame(0, $link->routingRules()->count());
    }

    private function preview($user, $workspace, ShortLink $link, array $context = [], ?array $draft = null): TestResponse
    {
        return $this->actingInWorkspace($user, $workspace)->postJson(route('short-links.routing-preview', $link), [
            'source' => $draft === null ? 'saved' : 'draft',
            'context' => ['timezone' => 'UTC', 'seed' => 'visitor-1', ...$context],
            ...($draft ?? []),
        ]);
    }

    private function conditionalRule(ShortLink $link, string $name, int $position, array $conditions, string $destination): RoutingRule
    {
        return $link->routingRules()->create([
            'name' => $name,
            'type' => RoutingRule::TYPE_CONDITIONAL,
            'position' => $position,
            'match_mode' => RoutingRule::MATCH_ALL,
            'conditions_version' => 1,
            'conditions' => $conditions,
            'destination_url' => $destination,
        ]);
    }

    private function splitRule(ShortLink $link, array $variants): RoutingRule
    {
        $rule = $link->routingRules()->create([
            'name' => 'Split',
            'type' => RoutingRule::TYPE_SPLIT_TEST,
            'position' => 1,
            'match_mode' => RoutingRule::MATCH_ALL,
            'conditions_version' => 1,
            'conditions' => [],
        ]);

        foreach ($variants as $position => $variant) {
            $rule->variants()->create(['position' => $position + 1, ...$variant]);
        }

        return $rule;
    }
}
