<?php

namespace Tests\Feature;

use App\Models\BioPage;
use App\Models\Domain;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BioPageOpenGraphTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_published_bio_page_has_a_generated_open_graph_image(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Acme', 'slug' => 'acme']);
        $domain = Domain::create([
            'workspace_id' => $workspace->id,
            'hostname' => 'bio.example.test',
            'status' => Domain::STATUS_ACTIVE,
            'verification_token' => str()->random(40),
            'verified_at' => now(),
            'dns_pointed_at' => now(),
        ]);
        $version = [
            'displayName' => 'Alice Example',
            'publicHandle' => '@alice',
            'biography' => 'Designer and creator',
            'theme' => [
                'backgroundColor' => '#17171c',
                'gradientColor' => '#4f46e5',
                'backgroundType' => 'gradient',
                'textColor' => '#f7f7f8',
            ],
        ];
        $bioPage = BioPage::create([
            'workspace_id' => $workspace->id,
            'draft_domain_id' => $domain->id,
            'published_domain_id' => $domain->id,
            'draft_slug' => 'alice',
            'published_slug' => 'alice',
            'draft' => $version,
            'published' => $version,
            'published_at' => now(),
        ]);

        $this->withHeader('Host', 'localhost')
            ->get(route('public.bio.open-graph', $bioPage, false))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }
}
