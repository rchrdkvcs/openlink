<?php

namespace Tests\Feature\SmartRouting;

use App\Models\AnalyticsEvent;
use App\Models\RoutingRule;
use App\Services\ShortLinks\SmartRouting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class RoutingRuleSyncTest extends TestCase
{
    use CreatesWorkspaces;
    use RefreshDatabase;

    public function test_editing_and_reordering_rules_preserves_historical_attribution(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'retained-rule');
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
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'retained-variant');
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
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'owned');
        $other = $this->shortLink($workspace, $domain, 'foreign');
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
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'variant-owner');
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
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'removed-rule');
        $routing = app(SmartRouting::class);
        $routing->sync($link, [['name' => 'Match', 'destination_url' => 'https://example.com/match']]);

        $this->withHeaders(['Host' => 'localhost'])->get('/removed-rule')->assertRedirect('https://example.com/match');
        $this->assertNotNull(AnalyticsEvent::query()->sole()->routing_rule_id);

        $routing->sync($link, []);

        $this->assertDatabaseCount('routing_rules', 0);
        $this->assertNull(AnalyticsEvent::query()->sole()->routing_rule_id);
    }
}
