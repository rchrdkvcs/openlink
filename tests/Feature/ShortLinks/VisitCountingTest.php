<?php

namespace Tests\Feature\ShortLinks;

use App\Services\Analytics\Outcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class VisitCountingTest extends TestCase
{
    use CreatesWorkspaces;
    use RefreshDatabase;

    public function test_public_resolution_redirects_and_counts_successful_visits(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'hello');

        $this->withHeader('Host', 'localhost')
            ->get('/hello')
            ->assertRedirect('https://example.com/landing');

        $this->assertSame(1, $link->fresh()->successful_visits);
        $this->assertDatabaseHas('analytics_events', [
            'short_link_id' => $link->id,
            'metric' => 'visit',
            'outcome' => Outcome::SUCCESS,
        ]);
    }

    public function test_visit_limit_only_counts_successful_destination_redirects(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'limited', [
            'destination_url' => 'https://example.com/limited',
            'visit_limit' => 1,
        ]);

        $this->withHeader('Host', 'localhost')->get('/limited')->assertRedirect('https://example.com/limited');
        $this->withHeader('Host', 'localhost')->get('/limited')->assertStatus(404);

        $this->assertSame(1, $link->fresh()->successful_visits);
        $this->assertDatabaseHas('analytics_events', [
            'short_link_id' => $link->id,
            'metric' => 'visit',
            'outcome' => Outcome::VISIT_LIMIT_REACHED,
        ]);
    }
}
