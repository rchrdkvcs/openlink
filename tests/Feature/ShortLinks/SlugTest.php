<?php

namespace Tests\Feature\ShortLinks;

use App\Services\SlugService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class SlugTest extends TestCase
{
    use CreatesWorkspaces;
    use RefreshDatabase;

    public function test_slug_service_rejects_reserved_slugs(): void
    {
        [, $domain] = $this->workspaceWithActiveDomain();
        $slugs = app(SlugService::class);

        $this->expectException(ValidationException::class);
        $slugs->validateCustom($domain, 'dashboard');
    }

    public function test_slug_service_rejects_duplicate_slugs_per_domain(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $slugs = app(SlugService::class);

        $this->shortLink($workspace, $domain, 'event/vip', ['destination_url' => 'https://example.com']);

        $this->expectException(ValidationException::class);
        $slugs->validateCustom($domain, 'event/vip');
    }

    public function test_short_url_can_be_changed_and_old_address_stops_resolving(): void
    {
        [$workspace, $domain, $user] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'before', ['destination_url' => 'https://example.com/target']);

        $this->get('/before')->assertRedirect('https://example.com/target');

        $this->actingInWorkspace($user, $workspace)
            ->patch(route('short-links.update', $link), $this->updatePayload($domain->id, 'after', 'https://example.com/target'))
            ->assertRedirect();

        $this->assertSame('after', $link->fresh()->slug);

        $this->get('/before')->assertNotFound();
        $this->get('/after')->assertRedirect('https://example.com/target');
    }

    public function test_short_url_change_rejects_slug_already_taken(): void
    {
        [$workspace, $domain, $user] = $this->workspaceWithActiveDomain();
        $this->shortLink($workspace, $domain, 'taken', ['destination_url' => 'https://example.com/taken']);
        $link = $this->shortLink($workspace, $domain, 'mine', ['destination_url' => 'https://example.com/mine']);

        $this->actingInWorkspace($user, $workspace)
            ->patch(route('short-links.update', $link), $this->updatePayload($domain->id, 'taken', 'https://example.com/mine'))
            ->assertSessionHasErrors('slug');

        $this->assertSame('mine', $link->fresh()->slug);
    }

    private function updatePayload(int $domainId, string $slug, string $destination): array
    {
        return [
            'folder_id' => null,
            'domain_id' => $domainId,
            'slug' => $slug,
            'destination_url' => $destination,
            'fallback_url' => null,
            'is_enabled' => true,
            'activates_at' => null,
            'expires_at' => null,
            'visit_limit' => null,
        ];
    }
}
