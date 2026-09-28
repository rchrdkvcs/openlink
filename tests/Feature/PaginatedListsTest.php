<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\ShortLink;
use App\Models\Tag;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaginatedListsTest extends TestCase
{
    use RefreshDatabase;

    public function test_short_links_are_paginated_and_filtered_on_the_server_for_web_and_api(): void
    {
        [$workspace, $domain, $user] = $this->workspace();
        foreach (range(1, 55) as $number) {
            $this->link($workspace, $domain, 'launch-'.$number);
        }
        $tag = Tag::create(['workspace_id' => $workspace->id, 'name' => 'featured']);
        ShortLink::query()->where('slug', 'launch-42')->firstOrFail()->tags()->attach($tag);

        $this->actingAs($user)
            ->get(route('links.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('linksPagination.total', 55)
                ->where('linksPagination.lastPage', 2)
                ->has('links', 50));

        $this->get(route('links.index', ['page' => 2]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('linksPagination.currentPage', 2)->has('links', 5));

        $this->getJson('/api/v1/links?page=2')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 55)
            ->assertJsonPath('meta.current_page', 2);

        $this->get(route('links.index', ['search' => 'LAUNCH-42']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('linksPagination.total', 1)
                ->where('links.0.slug', 'launch-42'));

        $this->get(route('links.index', ['search' => 'go.example.test/launch-42']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('linksPagination.total', 1));

        $this->getJson('/api/v1/links?tag=featured')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'launch-42');
    }

    public function test_short_link_status_filters_follow_lifecycle_precedence(): void
    {
        [$workspace, $domain, $user] = $this->workspace();
        $this->link($workspace, $domain, 'active');
        $this->link($workspace, $domain, 'disabled', ['is_enabled' => false]);
        $this->link($workspace, $domain, 'scheduled', ['activates_at' => now()->addDay()]);
        $this->link($workspace, $domain, 'expired', ['expires_at' => now()->subDay()]);
        $this->link($workspace, $domain, 'limit', ['visit_limit' => 1, 'successful_visits' => 1]);
        $this->link($workspace, $domain, 'archived', ['archived_at' => now(), 'is_enabled' => false]);

        $this->actingAs($user);
        foreach (['active' => 1, 'disabled' => 1, 'scheduled' => 1, 'expired' => 2, 'archived' => 1] as $status => $count) {
            $this->get(route('links.index', ['status' => $status]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page->where('linksPagination.total', $count));
        }

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('linkCounts.total', 6)
                ->where('linkCounts.active', 1));

        $this->getJson('/api/v1/links')
            ->assertOk()
            ->assertJsonPath('meta.total', 6);
    }

    public function test_qr_codes_are_paginated_and_searchable_and_selected_short_link_remains_available(): void
    {
        [$workspace, $domain, $user] = $this->workspace();
        $selected = $this->link($workspace, $domain, 'oldest');
        foreach (range(1, 55) as $number) {
            $this->link($workspace, $domain, 'newer-'.$number);
        }

        foreach (range(1, 26) as $number) {
            $workspace->qrCodes()->create([
                'name' => 'QR '.$number,
                'token' => 'qr-'.$number,
                'payload_type' => 'text',
                'payload' => ['content' => 'example'],
                'content' => 'example',
            ]);
        }

        $this->actingAs($user)
            ->get(route('qr-codes.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('qrCodes', 24)
                ->has('shortLinks', 50)
                ->where('qrPagination.total', 26));

        $this->get(route('qr-codes.index', ['page' => 2]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('qrCodes', 2));

        $this->get(route('qr-codes.index', ['search' => 'QR 26']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('qrPagination.total', 1)->where('qrCodes.0.name', 'QR 26'));

        $this->get(route('qr-codes.short-links', ['search' => 'OLDEST']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $selected->id);

        $this->get(route('qr-codes.short-links', ['search' => 'go.example.test/oldest']))
            ->assertOk()
            ->assertJsonPath('data.0.id', $selected->id);

        $qrCode = $workspace->qrCodes()->create([
            'name' => 'Linked',
            'token' => 'linked-qr',
            'short_link_id' => $selected->id,
        ]);
        $this->get(route('qr-codes.show', $qrCode))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('shortLinks.0.id', $selected->id));
    }

    /** @return array{Workspace, Domain, User} */
    private function workspace(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Pagination',
            'slug' => 'pagination',
            'settings' => [],
        ]);
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMember::ROLE_OWNER,
        ]);
        $domain = Domain::create([
            'workspace_id' => $workspace->id,
            'hostname' => 'go.example.test',
            'status' => Domain::STATUS_ACTIVE,
            'verification_token' => 'pagination-token',
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
