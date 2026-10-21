<?php

namespace Tests\Feature\ShortLinks;

use App\Models\AnalyticsEvent;
use App\Models\Domain;
use App\Models\RoutingRule;
use App\Models\ShortLink;
use App\Models\UtmTemplate;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class UtmDestinationTest extends TestCase
{
    use CreatesWorkspaces;
    use RefreshDatabase;

    private const ROUTED = 'https://example.com/spring?utm_source=newsletter&utm_campaign=spring';

    private const BUILT = 'https://example.com/caf%C3%A9?a=1&a=2&q=café&next=%2Fhome%3Fx%3D1&utm_source=newsletter&utm_campaign=%C3%A9t%C3%A9%20sale#section';

    public function test_editor_saves_a_built_destination_verbatim_through_the_link_update_path(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $editor = $this->memberOf($workspace, WorkspaceMember::ROLE_EDITOR);
        $link = $this->shortLink($workspace, $domain, 'launch');

        $this->actingInWorkspace($editor, $workspace)
            ->patch(route('short-links.update', $link), $this->updatePayload($link->slug, $domain->id, self::BUILT))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(self::BUILT, $link->fresh()->destination_url);

        $this->withHeader('Host', 'localhost')
            ->get('/launch')
            ->assertRedirect()
            ->assertHeader('Location', self::BUILT);
    }

    public function test_viewer_cannot_save_a_built_destination(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $viewer = $this->memberOf($workspace, WorkspaceMember::ROLE_VIEWER);
        $link = $this->shortLink($workspace, $domain, 'launch');

        $this->actingInWorkspace($viewer, $workspace)
            ->patch(route('short-links.update', $link), $this->updatePayload($link->slug, $domain->id, self::BUILT))
            ->assertForbidden();

        $this->assertSame('https://example.com/landing', $link->fresh()->destination_url);
    }

    public function test_built_destinations_still_pass_destination_validation_and_loop_checks(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'self');

        $this->actingInWorkspace($owner, $workspace)
            ->patch(route('short-links.update', $link), $this->updatePayload('self', $domain->id, 'http://localhost/self?utm_source=newsletter#top'))
            ->assertSessionHasErrors('destination_url');

        $this->actingInWorkspace($owner, $workspace)
            ->patch(route('short-links.update', $link), $this->updatePayload('self', $domain->id, 'ftp://example.com/?utm_source=newsletter'))
            ->assertSessionHasErrors('destination_url');

        $this->assertSame('https://example.com/landing', $link->fresh()->destination_url);
    }

    public function test_updating_or_deleting_a_template_does_not_rewrite_existing_destinations(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $template = UtmTemplate::create([
            'workspace_id' => $workspace->id,
            'name' => 'Newsletter',
            'utm_source' => 'newsletter',
            'utm_medium' => 'email',
        ]);
        $destination = 'https://example.com/landing?utm_source=newsletter&utm_medium=email';
        $link = $this->shortLink($workspace, $domain, 'mail', ['destination_url' => $destination]);

        $this->actingInWorkspace($owner, $workspace)
            ->patch(route('utm-templates.update', $template), ['name' => 'Newsletter', 'utm_source' => 'digest', 'utm_medium' => 'mail'])
            ->assertSessionHasNoErrors();

        $this->assertSame($destination, $link->fresh()->destination_url);

        $this->actingInWorkspace($owner, $workspace)
            ->delete(route('utm-templates.destroy', $template))
            ->assertRedirect();

        $this->assertSame($destination, $link->fresh()->destination_url);
    }

    public function test_default_destination_with_utm_parameters_records_the_visitor_request_utm(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $link = $this->routedLink($workspace, $domain);

        $this->withHeaders(['Host' => 'localhost', 'CF-IPCountry' => 'DE'])
            ->get('/spring?utm_source=poster&utm_medium=print')
            ->assertRedirect(self::ROUTED);

        $event = AnalyticsEvent::query()->sole();

        $this->assertNull($event->routing_rule_id);
        $this->assertSame('poster', $event->utm_source);
        $this->assertSame('print', $event->utm_medium);
        $this->assertNull($event->utm_campaign);
        $this->assertSame(1, $link->fresh()->successful_visits);
    }

    public function test_routing_rules_still_override_a_default_destination_with_utm_parameters(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $link = $this->routedLink($workspace, $domain);

        $this->withHeaders(['Host' => 'localhost', 'CF-IPCountry' => 'FR'])
            ->get('/spring')
            ->assertRedirect('https://example.com/fr');

        $event = AnalyticsEvent::query()->sole();

        $this->assertSame($link->routingRules()->sole()->id, $event->routing_rule_id);
        $this->assertNull($event->utm_source);
    }

    private function routedLink(Workspace $workspace, Domain $domain): ShortLink
    {
        $link = $this->shortLink($workspace, $domain, 'spring', ['destination_url' => self::ROUTED]);
        $link->routingRules()->create([
            'name' => 'France',
            'type' => RoutingRule::TYPE_CONDITIONAL,
            'position' => 1,
            'match_mode' => RoutingRule::MATCH_ALL,
            'conditions_version' => 1,
            'conditions' => [['type' => 'country', 'operator' => 'is', 'value' => 'FR']],
            'destination_url' => 'https://example.com/fr',
        ]);

        return $link;
    }

    private function updatePayload(string $slug, int $domainId, string $destination): array
    {
        return [
            'destination_url' => $destination,
            'domain_id' => $domainId,
            'slug' => $slug,
            'is_enabled' => true,
        ];
    }
}
