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
use Illuminate\Foundation\Testing\RefreshDatabase;
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
