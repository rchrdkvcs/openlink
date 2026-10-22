<?php

namespace Tests\Feature\SmartRouting;

use App\Enums\WorkspaceRole;
use App\Models\RoutingRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class RoutingEditorPageTest extends TestCase
{
    use CreatesWorkspaces;
    use RefreshDatabase;

    public function test_members_open_the_routing_editor_with_the_saved_rules(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'editor');
        $link->routingRules()->create([
            'name' => 'France', 'type' => RoutingRule::TYPE_CONDITIONAL, 'position' => 1, 'match_mode' => 'all',
            'conditions_version' => 1, 'conditions' => [['type' => 'country', 'operator' => 'is', 'value' => 'FR']],
            'destination_url' => 'https://example.com/fr',
        ]);
        $viewer = $this->memberOf($workspace, WorkspaceRole::Viewer->value);

        $this->actingInWorkspace($viewer, $workspace)
            ->get(route('links.routing', $link))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Links/Routing')
                ->where('link.id', $link->id)
                ->where('link.routing_rules.0.name', 'France')
                ->has('routingSchema.conditionTypes')
                ->where('canEditWorkspace', false));
    }

    public function test_links_from_another_workspace_are_not_opened_or_changed(): void
    {
        [$workspace, , $owner] = $this->workspaceWithActiveDomain();
        [$otherWorkspace, $otherDomain] = $this->workspaceWithActiveDomain('other.example.test');
        $foreign = $this->shortLink($otherWorkspace, $otherDomain, 'foreign');

        $this->actingInWorkspace($owner, $workspace)->get(route('links.routing', $foreign))->assertForbidden();
        $this->actingInWorkspace($owner, $workspace)
            ->put(route('short-links.routing.update', $foreign), ['destination_url' => 'https://example.com/x', 'routing_rules' => []])
            ->assertForbidden();

        $this->assertSame('https://example.com/landing', $foreign->fresh()->destination_url);
    }

    public function test_editor_saves_the_default_destination_and_ordered_rules(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'ordered');
        $editor = $this->memberOf($workspace, WorkspaceRole::Editor->value);

        $this->actingInWorkspace($editor, $workspace)
            ->put(route('short-links.routing.update', $link), [
                'destination_url' => 'https://example.com/new-default',
                'routing_rules' => [
                    ['name' => 'Mobile', 'destination_url' => 'https://example.com/mobile', 'conditions' => [['type' => 'device_type', 'operator' => 'is', 'value' => 'mobile']]],
                    ['name' => 'Split', 'type' => RoutingRule::TYPE_SPLIT_TEST, 'conditions' => [], 'variants' => [
                        ['name' => 'A', 'destination_url' => 'https://example.com/a', 'weight' => 25],
                        ['name' => 'B', 'destination_url' => 'https://example.com/b', 'weight' => 75],
                    ]],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $link->refresh();
        $this->assertSame('https://example.com/new-default', $link->destination_url);
        $this->assertSame(['Mobile', 'Split'], $link->routingRules()->orderBy('position')->pluck('name')->all());
        $this->assertSame([25, 75], $link->routingRules()->where('name', 'Split')->sole()->variants->pluck('weight')->all());
    }

    public function test_viewer_cannot_change_routing(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'read-only');
        $viewer = $this->memberOf($workspace, WorkspaceRole::Viewer->value);

        $this->actingInWorkspace($viewer, $workspace)
            ->put(route('short-links.routing.update', $link), ['destination_url' => 'https://example.com/x', 'routing_rules' => []])
            ->assertForbidden();

        $this->assertSame('https://example.com/landing', $link->fresh()->destination_url);
    }

    public function test_invalid_or_looping_routing_is_rejected_without_changes(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'loop');

        $this->actingInWorkspace($owner, $workspace)
            ->put(route('short-links.routing.update', $link), ['destination_url' => 'https://localhost/loop', 'routing_rules' => []])
            ->assertSessionHasErrors('destination_url');

        $this->actingInWorkspace($owner, $workspace)
            ->put(route('short-links.routing.update', $link), ['destination_url' => 'https://example.com/ok', 'routing_rules' => [
                ['destination_url' => 'https://localhost/loop', 'conditions' => []],
            ]])
            ->assertSessionHasErrors('routing_rules.0.destination_url');

        $this->assertSame('https://example.com/landing', $link->fresh()->destination_url);
        $this->assertSame(0, $link->routingRules()->count());
    }

    public function test_saving_without_a_routing_rule_list_keeps_the_existing_rules(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'kept');
        $link->routingRules()->create([
            'name' => 'France', 'type' => RoutingRule::TYPE_CONDITIONAL, 'position' => 1, 'match_mode' => 'all',
            'conditions_version' => 1, 'conditions' => [], 'destination_url' => 'https://example.com/fr',
        ]);

        foreach ([['destination_url' => 'https://example.com/x'], ['destination_url' => 'https://example.com/x', 'routing_rules' => null]] as $payload) {
            $this->actingInWorkspace($owner, $workspace)
                ->put(route('short-links.routing.update', $link), $payload)
                ->assertSessionHasErrors('routing_rules');
        }

        $this->assertSame(['France'], $link->routingRules()->pluck('name')->all());
        $this->assertSame('https://example.com/landing', $link->fresh()->destination_url);

        $this->actingInWorkspace($owner, $workspace)
            ->put(route('short-links.routing.update', $link), ['destination_url' => 'https://example.com/x', 'routing_rules' => []])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $link->routingRules()->count());
    }
}
