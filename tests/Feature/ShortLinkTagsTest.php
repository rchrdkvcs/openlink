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

class ShortLinkTagsTest extends TestCase
{
    use RefreshDatabase;

    public function test_short_link_tags_can_be_replaced_and_cleared_on_update(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Tags Workspace',
            'slug' => 'tags-workspace',
        ]);
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMember::ROLE_OWNER,
        ]);
        $domain = Domain::create([
            'workspace_id' => $workspace->id,
            'hostname' => 'localhost',
            'status' => Domain::STATUS_ACTIVE,
            'verification_token' => 'tags-workspace-token',
        ]);
        $link = ShortLink::create([
            'workspace_id' => $workspace->id,
            'domain_id' => $domain->id,
            'slug' => 'tagged',
            'destination_url' => 'https://example.com',
        ]);
        $oldTag = Tag::create(['workspace_id' => $workspace->id, 'name' => 'old']);
        $link->tags()->attach($oldTag);

        $request = fn (array $fields) => $this->actingAs($user)
            ->withSession(['workspace_id' => $workspace->id])
            ->patch(route('short-links.update', $link), array_merge([
                'domain_id' => $domain->id,
                'slug' => $link->slug,
                'destination_url' => $link->destination_url,
                'is_enabled' => true,
            ], $fields))->assertRedirect();

        $request(['tags' => 'old, new']);
        $this->assertEqualsCanonicalizing(['old', 'new'], $link->tags()->pluck('name')->all());

        $request([]);
        $this->assertEqualsCanonicalizing(['old', 'new'], $link->tags()->pluck('name')->all());

        $request(['tags' => 'new, other']);
        $this->assertEqualsCanonicalizing(['new', 'other'], $link->tags()->pluck('name')->all());

        $request(['tags' => '']);
        $this->assertCount(0, $link->tags()->get());
    }
}
