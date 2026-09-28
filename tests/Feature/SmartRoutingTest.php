<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\Domain;
use App\Models\RoutingRule;
use App\Models\ShortLink;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Analytics\Outcome;
use App\Services\ShortLinks\SmartRouting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SmartRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_matching_routing_rule_chooses_the_destination_and_records_analytics(): void
    {
        [$workspace, $domain] = $this->workspaceAndDomain();
        $link = $this->link($workspace, $domain, 'smart');
        $rule = $link->routingRules()->create([
            'name' => 'France',
            'type' => RoutingRule::TYPE_CONDITIONAL,
            'position' => 1,
            'match_mode' => RoutingRule::MATCH_ALL,
            'conditions_version' => 1,
            'conditions' => [
                ['type' => 'country', 'operator' => 'is', 'value' => 'FR'],
            ],
            'destination_url' => 'https://example.com/fr',
        ]);
        $link->routingRules()->create([
            'name' => 'Mobile',
            'type' => RoutingRule::TYPE_CONDITIONAL,
            'position' => 2,
            'match_mode' => RoutingRule::MATCH_ALL,
            'conditions_version' => 1,
            'conditions' => [
                ['type' => 'device_type', 'operator' => 'is', 'value' => 'mobile'],
            ],
            'destination_url' => 'https://example.com/mobile',
        ]);

        $this->withHeaders([
            'Host' => 'localhost',
            'CF-IPCountry' => 'FR',
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 Chrome/126.0.0.0 Mobile Safari/537.36',
        ])->get('/smart')->assertRedirect('https://example.com/fr');

        $this->assertSame(1, $link->fresh()->successful_visits);
        $this->assertDatabaseHas('analytics_events', [
            'short_link_id' => $link->id,
            'routing_rule_id' => $rule->id,
            'routing_variant_id' => null,
            'outcome' => Outcome::SUCCESS,
        ]);
    }

    public function test_default_destination_is_used_when_no_routing_rule_matches(): void
    {
        [$workspace, $domain] = $this->workspaceAndDomain();
        $link = $this->link($workspace, $domain, 'default');
        $link->routingRules()->create([
            'name' => 'France',
            'type' => RoutingRule::TYPE_CONDITIONAL,
            'position' => 1,
            'match_mode' => RoutingRule::MATCH_ALL,
            'conditions_version' => 1,
            'conditions' => [
                ['type' => 'country', 'operator' => 'is', 'value' => 'FR'],
            ],
            'destination_url' => 'https://example.com/fr',
        ]);

        $this->withHeaders(['Host' => 'localhost', 'CF-IPCountry' => 'DE'])
            ->get('/default')
            ->assertRedirect('https://example.com/default');

        $event = AnalyticsEvent::query()->sole();

        $this->assertNull($event->routing_rule_id);
        $this->assertNull($event->routing_variant_id);
    }

    public function test_split_test_rule_uses_weighted_variants_and_records_the_variant(): void
    {
        [$workspace, $domain] = $this->workspaceAndDomain();
        $link = $this->link($workspace, $domain, 'split');
        $rule = $link->routingRules()->create([
            'name' => 'Homepage test',
            'type' => RoutingRule::TYPE_SPLIT_TEST,
            'position' => 1,
            'match_mode' => RoutingRule::MATCH_ALL,
            'conditions_version' => 1,
            'conditions' => [],
        ]);
        $disabled = $rule->variants()->create([
            'name' => 'Paused',
            'position' => 1,
            'is_enabled' => false,
            'destination_url' => 'https://example.com/paused',
            'weight' => 100,
        ]);
        $variantA = $rule->variants()->create([
            'name' => 'A',
            'position' => 2,
            'destination_url' => 'https://example.com/winner',
            'weight' => 100,
        ]);
        $variantB = $rule->variants()->create([
            'name' => 'B',
            'position' => 3,
            'destination_url' => 'https://example.com/winner',
            'weight' => 100,
        ]);

        $this->withHeaders(['Host' => 'localhost', 'User-Agent' => 'Mozilla/5.0'])
            ->get('/split')
            ->assertRedirect('https://example.com/winner');

        $event = AnalyticsEvent::query()->sole();

        $this->assertSame($link->id, $event->short_link_id);
        $this->assertSame($rule->id, $event->routing_rule_id);
        $this->assertContains($event->routing_variant_id, [$variantA->id, $variantB->id]);
        $this->assertDatabaseMissing('analytics_events', ['routing_variant_id' => $disabled->id]);
    }

    public function test_smart_routing_does_not_bypass_password_protection(): void
    {
        [$workspace, $domain] = $this->workspaceAndDomain();
        $link = $this->link($workspace, $domain, 'secret', [
            'password_hash' => bcrypt('opensesame'),
        ]);
        $link->routingRules()->create([
            'name' => 'France',
            'type' => RoutingRule::TYPE_CONDITIONAL,
            'position' => 1,
            'match_mode' => RoutingRule::MATCH_ALL,
            'conditions_version' => 1,
            'conditions' => [
                ['type' => 'country', 'operator' => 'is', 'value' => 'FR'],
            ],
            'destination_url' => 'https://example.com/fr',
        ]);

        $this->withHeaders(['Host' => 'localhost', 'CF-IPCountry' => 'FR'])
            ->get('/secret')
            ->assertOk();

        $this->assertSame(0, $link->fresh()->successful_visits);
        $this->assertDatabaseCount('analytics_events', 0);
    }

    public function test_editing_and_reordering_rules_preserves_historical_attribution(): void
    {
        [$workspace, $domain] = $this->workspaceAndDomain();
        $link = $this->link($workspace, $domain, 'retained-rule');
        $routing = app(SmartRouting::class);
        $routing->sync($link, [
            ['name' => 'France', 'destination_url' => 'https://example.com/fr', 'conditions' => [
                ['type' => 'country', 'operator' => 'is', 'value' => 'FR'],
            ]],
            ['name' => 'Other', 'destination_url' => 'https://example.com/other', 'conditions' => []],
        ]);
        $ruleIds = $link->routingRules()->orderBy('position')->pluck('id')->all();

        $this->withHeaders(['Host' => 'localhost', 'CF-IPCountry' => 'FR'])
            ->get('/retained-rule')->assertRedirect('https://example.com/fr');

        $routing->sync($link, [
            ['id' => $ruleIds[1], 'name' => 'Other', 'destination_url' => 'https://example.com/other', 'conditions' => []],
            ['id' => $ruleIds[0], 'name' => 'France updated', 'destination_url' => 'https://example.com/fr-new', 'conditions' => [
                ['type' => 'country', 'operator' => 'is', 'value' => 'FR'],
            ]],
        ]);

        $this->assertSame([$ruleIds[1], $ruleIds[0]], $link->routingRules()->orderBy('position')->pluck('id')->all());
        $this->assertSame($ruleIds[0], AnalyticsEvent::query()->sole()->routing_rule_id);
    }

    public function test_editing_split_test_preserves_variant_attribution(): void
    {
        [$workspace, $domain] = $this->workspaceAndDomain();
        $link = $this->link($workspace, $domain, 'retained-variant');
        $routing = app(SmartRouting::class);
        $routing->sync($link, [[
            'name' => 'Split', 'type' => RoutingRule::TYPE_SPLIT_TEST, 'conditions' => [],
            'variants' => [
                ['name' => 'A', 'destination_url' => 'https://example.com/a', 'weight' => 50],
                ['name' => 'B', 'destination_url' => 'https://example.com/b', 'weight' => 50],
            ],
        ]]);
        $rule = $link->routingRules()->with('variants')->sole();
        $variantIds = $rule->variants->pluck('id')->all();

        $this->withHeaders(['Host' => 'localhost'])->get('/retained-variant')->assertRedirect();
        $attributedVariantId = AnalyticsEvent::query()->sole()->routing_variant_id;

        $routing->sync($link, [[
            'id' => $rule->id, 'name' => 'Split updated', 'type' => RoutingRule::TYPE_SPLIT_TEST, 'conditions' => [],
            'variants' => [
                ['id' => $variantIds[1], 'name' => 'B', 'destination_url' => 'https://example.com/b', 'weight' => 60],
                ['id' => $variantIds[0], 'name' => 'A', 'destination_url' => 'https://example.com/a', 'weight' => 40],
            ],
        ]]);

        $this->assertSame([$variantIds[1], $variantIds[0]], $rule->variants()->orderBy('position')->pluck('id')->all());
        $this->assertSame($attributedVariantId, AnalyticsEvent::query()->sole()->routing_variant_id);
    }

    public function test_sync_rejects_identifiers_from_another_link_without_changing_existing_rules(): void
    {
        [$workspace, $domain] = $this->workspaceAndDomain();
        $link = $this->link($workspace, $domain, 'owned');
        $other = $this->link($workspace, $domain, 'foreign');
        $routing = app(SmartRouting::class);
        $routing->sync($link, [['name' => 'Mine', 'destination_url' => 'https://example.com/mine']]);
        $routing->sync($other, [['name' => 'Theirs', 'destination_url' => 'https://example.com/theirs']]);
        $foreignId = $other->routingRules()->sole()->id;

        try {
            $routing->sync($link, [['id' => $foreignId, 'name' => 'Stolen', 'destination_url' => 'https://example.com/stolen']]);
            $this->fail('A foreign routing rule ID must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('routing_rules.0.id', $exception->errors());
        }

        $this->assertSame('Mine', $link->routingRules()->sole()->name);
    }

    public function test_sync_rejects_a_variant_from_another_rule(): void
    {
        [$workspace, $domain] = $this->workspaceAndDomain();
        $link = $this->link($workspace, $domain, 'variant-owner');
        $routing = app(SmartRouting::class);
        $split = fn (string $name) => [
            'name' => $name, 'type' => RoutingRule::TYPE_SPLIT_TEST, 'conditions' => [],
            'variants' => [
                ['name' => 'A', 'destination_url' => 'https://example.com/a'],
                ['name' => 'B', 'destination_url' => 'https://example.com/b'],
            ],
        ];
        $routing->sync($link, [$split('First'), $split('Second')]);
        $rules = $link->routingRules()->with('variants')->get();

        try {
            $routing->sync($link, [[
                ...$split('First'), 'id' => $rules[0]->id,
                'variants' => [
                    ['id' => $rules[1]->variants[0]->id, 'name' => 'A', 'destination_url' => 'https://example.com/a'],
                    ['id' => $rules[0]->variants[1]->id, 'name' => 'B', 'destination_url' => 'https://example.com/b'],
                ],
            ], [
                ...$split('Second'), 'id' => $rules[1]->id,
                'variants' => $rules[1]->variants->map(fn ($variant) => [
                    'id' => $variant->id, 'name' => $variant->name, 'destination_url' => $variant->destination_url,
                ])->all(),
            ]]);
            $this->fail('A variant from another rule must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('routing_rules.0.variants.0.id', $exception->errors());
        }

        $this->assertSame(2, $link->routingRules()->count());
        $this->assertSame(2, $rules[0]->variants()->count());
    }

    public function test_removing_a_rule_explicitly_clears_its_historical_attribution(): void
    {
        [$workspace, $domain] = $this->workspaceAndDomain();
        $link = $this->link($workspace, $domain, 'removed-rule');
        $routing = app(SmartRouting::class);
        $routing->sync($link, [['name' => 'Match', 'destination_url' => 'https://example.com/match']]);

        $this->withHeaders(['Host' => 'localhost'])->get('/removed-rule')->assertRedirect('https://example.com/match');
        $this->assertNotNull(AnalyticsEvent::query()->sole()->routing_rule_id);

        $routing->sync($link, []);

        $this->assertDatabaseCount('routing_rules', 0);
        $this->assertNull(AnalyticsEvent::query()->sole()->routing_rule_id);
    }

    /** @return array{Workspace, Domain, User} */
    private function workspaceAndDomain(string $hostname = 'localhost'): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Routing Co',
            'slug' => 'routing-co',
            'settings' => [],
        ]);
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMember::ROLE_OWNER,
        ]);
        $domain = Domain::create([
            'workspace_id' => $workspace->id,
            'hostname' => $hostname,
            'status' => Domain::STATUS_ACTIVE,
            'verification_token' => 'test-token-'.str()->random(12),
            'verified_at' => now(),
        ]);

        return [$workspace, $domain, $user];
    }

    private function link(Workspace $workspace, Domain $domain, string $slug, array $attributes = []): ShortLink
    {
        return ShortLink::create([
            'workspace_id' => $workspace->id,
            'domain_id' => $domain->id,
            'slug' => $slug,
            'destination_url' => 'https://example.com/'.$slug,
            ...$attributes,
        ]);
    }
}
