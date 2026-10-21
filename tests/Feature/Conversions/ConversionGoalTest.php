<?php

namespace Tests\Feature\Conversions;

use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class ConversionGoalTest extends TestCase
{
    use CreatesWorkspaces;
    use RefreshDatabase;

    public function test_links_are_created_without_a_conversion_goal(): void
    {
        [, $domain, $owner] = $this->workspaceWithActiveDomain();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/links', ['domain_id' => $domain->id, 'destination_url' => 'https://example.com', 'slug' => 'new'])
            ->assertCreated()
            ->assertJsonPath('data.conversion_goal', null);
    }

    public function test_editors_set_keep_and_remove_a_conversion_goal(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'signup');
        Sanctum::actingAs($this->memberOf($workspace, WorkspaceMember::ROLE_EDITOR));
        $base = ['destination_url' => 'https://example.com/landing', 'is_enabled' => true];

        $this->patchJson("/api/v1/links/{$link->id}", [...$base, 'conversion_goal' => '  Registration '])
            ->assertOk()
            ->assertJsonPath('data.conversion_goal', 'Registration');
        $this->patchJson("/api/v1/links/{$link->id}", $base)
            ->assertOk()
            ->assertJsonPath('data.conversion_goal', 'Registration');
        $this->patchJson("/api/v1/links/{$link->id}", [...$base, 'conversion_goal' => ''])
            ->assertOk()
            ->assertJsonPath('data.conversion_goal', null);

        $this->assertFalse($link->fresh()->tracksConversions());
    }

    public function test_conversion_goal_names_are_short_and_viewers_cannot_change_them(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'signup');
        $base = ['destination_url' => 'https://example.com/landing', 'is_enabled' => true];

        Sanctum::actingAs($owner);
        $this->patchJson("/api/v1/links/{$link->id}", [...$base, 'conversion_goal' => str_repeat('a', 81)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('conversion_goal');

        Sanctum::actingAs($this->memberOf($workspace, WorkspaceMember::ROLE_VIEWER));
        $this->patchJson("/api/v1/links/{$link->id}", [...$base, 'conversion_goal' => 'Registration'])
            ->assertForbidden();

        $this->assertNull($link->fresh()->conversion_goal);
    }
}
