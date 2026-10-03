<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\ShortLink;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShortLinkWritePathTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_destination_pointing_at_its_own_short_url_is_a_field_error(): void
    {
        [, $domain] = $this->actingAsOwner();

        $this->postJson('/api/v1/links', [
            'domain_id' => $domain->id,
            'slug' => 'loop',
            'destination_url' => 'https://go.example.test/loop/',
        ])->assertUnprocessable()->assertJsonValidationErrors('destination_url');

        $this->assertDatabaseCount('short_links', 0);
    }

    public function test_routing_loops_are_checked_against_the_new_short_url_when_the_slug_changes(): void
    {
        [$workspace, $domain] = $this->actingAsOwner();
        $link = $this->link($workspace, $domain, 'before');

        $this->patchJson('/api/v1/links/'.$link->id, [
            'slug' => 'after',
            'destination_url' => 'https://example.com',
            'is_enabled' => true,
            'routing_rules' => [['name' => 'Loop', 'destination_url' => 'https://go.example.test/after']],
        ])->assertUnprocessable()->assertJsonValidationErrors('routing_rules.0.destination_url');

        $this->assertSame('before', $link->fresh()->slug);
        $this->assertDatabaseCount('routing_rules', 0);
    }

    public function test_routing_rules_are_validated_at_the_input_seam(): void
    {
        $this->actingAsOwner();

        $this->postJson('/api/v1/links', [
            'destination_url' => 'https://example.com',
            'routing_rules' => [['conditions' => [['type' => 'planet', 'operator' => 'is']], 'destination_url' => 'nope']],
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'routing_rules.0.conditions.0.type',
            'routing_rules.0.destination_url',
        ]);
    }

    public function test_unusable_domains_are_reported_on_the_domain_field(): void
    {
        [$workspace] = $this->actingAsOwner();
        $pending = Domain::create([
            'workspace_id' => $workspace->id,
            'hostname' => 'pending.example.test',
            'status' => Domain::STATUS_PENDING,
            'verification_token' => 'pending-token',
        ]);
        $foreign = Domain::create([
            'workspace_id' => Workspace::create(['owner_id' => User::factory()->create()->id, 'name' => 'Other', 'slug' => 'other'])->id,
            'hostname' => 'foreign.example.test',
            'status' => Domain::STATUS_ACTIVE,
            'verification_token' => 'foreign-token',
        ]);

        foreach ([$pending, $foreign] as $domain) {
            $this->postJson('/api/v1/links', ['domain_id' => $domain->id, 'destination_url' => 'https://example.com'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('domain_id');
        }
    }

    public function test_keeping_the_short_url_does_not_revalidate_the_slug(): void
    {
        [$workspace, $domain] = $this->actingAsOwner();
        $link = $this->link($workspace, $domain, 'Legacy Slug');

        $this->patchJson('/api/v1/links/'.$link->id, [
            'slug' => 'Legacy Slug',
            'destination_url' => 'https://example.com/new',
            'is_enabled' => true,
        ])->assertOk()->assertJsonPath('data.destination_url', 'https://example.com/new');
    }

    private function actingAsOwner(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $user->id, 'name' => 'Writers', 'slug' => 'writers']);
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => WorkspaceMember::ROLE_OWNER]);
        $domain = Domain::create([
            'workspace_id' => $workspace->id,
            'hostname' => 'go.example.test',
            'status' => Domain::STATUS_ACTIVE,
            'verification_token' => 'writers-token',
            'verified_at' => now(),
        ]);
        Sanctum::actingAs($user);

        return [$workspace, $domain, $user];
    }

    private function link(Workspace $workspace, Domain $domain, string $slug): ShortLink
    {
        return ShortLink::create([
            'workspace_id' => $workspace->id,
            'domain_id' => $domain->id,
            'slug' => $slug,
            'destination_url' => 'https://example.com/'.$slug,
        ]);
    }
}
