<?php

namespace Tests\Feature\UtmTemplates;

use App\Models\User;
use App\Models\UtmTemplate;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class UtmTemplateManagementTest extends TestCase
{
    use CreatesWorkspaces;
    use RefreshDatabase;

    public function test_owner_can_create_rename_update_and_delete_a_template(): void
    {
        [$workspace, $owner] = $this->workspaceWithOwner();

        $this->actingInWorkspace($owner, $workspace)
            ->post(route('utm-templates.store'), [
                'name' => 'Newsletter',
                'utm_source' => 'Newsletter',
                'utm_medium' => 'email',
                'utm_campaign' => '',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $template = UtmTemplate::query()->sole();
        $this->assertSame($workspace->id, $template->workspace_id);
        $this->assertSame('Newsletter', $template->utm_source);
        $this->assertSame('email', $template->utm_medium);
        $this->assertNull($template->utm_campaign);

        $this->actingInWorkspace($owner, $workspace)
            ->patch(route('utm-templates.update', $template), [
                'name' => 'Weekly newsletter',
                'utm_source' => 'newsletter',
                'utm_campaign' => 'weekly',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $template->refresh();
        $this->assertSame('Weekly newsletter', $template->name);
        $this->assertSame('newsletter', $template->utm_source);
        $this->assertNull($template->utm_medium);
        $this->assertSame('weekly', $template->utm_campaign);

        $this->actingInWorkspace($owner, $workspace)
            ->delete(route('utm-templates.destroy', $template))
            ->assertRedirect();

        $this->assertDatabaseCount('utm_templates', 0);
    }

    public function test_admin_can_manage_templates(): void
    {
        [$workspace] = $this->workspaceWithOwner();
        $admin = $this->memberOf($workspace, WorkspaceMember::ROLE_ADMIN);

        $this->actingInWorkspace($admin, $workspace)
            ->post(route('utm-templates.store'), ['name' => 'Ads', 'utm_source' => 'google'])
            ->assertSessionHasNoErrors();

        $template = UtmTemplate::query()->sole();

        $this->actingInWorkspace($admin, $workspace)
            ->patch(route('utm-templates.update', $template), ['name' => 'Paid search', 'utm_source' => 'google'])
            ->assertSessionHasNoErrors();

        $this->actingInWorkspace($admin, $workspace)
            ->delete(route('utm-templates.destroy', $template))
            ->assertRedirect();

        $this->assertDatabaseCount('utm_templates', 0);
    }

    #[DataProvider('nonManagerRoles')]
    public function test_editors_and_viewers_cannot_mutate_templates(string $role): void
    {
        [$workspace] = $this->workspaceWithOwner();
        $member = $this->memberOf($workspace, $role);
        $template = $this->template($workspace, 'Newsletter', ['utm_source' => 'newsletter']);

        $this->actingInWorkspace($member, $workspace)
            ->post(route('utm-templates.store'), ['name' => 'Hijack', 'utm_source' => 'x'])
            ->assertForbidden();

        $this->actingInWorkspace($member, $workspace)
            ->patch(route('utm-templates.update', $template), ['name' => 'Hijacked', 'utm_source' => 'x'])
            ->assertForbidden();

        $this->actingInWorkspace($member, $workspace)
            ->delete(route('utm-templates.destroy', $template))
            ->assertForbidden();

        $this->assertDatabaseCount('utm_templates', 1);
        $this->assertDatabaseHas('utm_templates', ['id' => $template->id, 'name' => 'Newsletter', 'utm_source' => 'newsletter']);
    }

    public static function nonManagerRoles(): array
    {
        return [
            'editor' => [WorkspaceMember::ROLE_EDITOR],
            'viewer' => [WorkspaceMember::ROLE_VIEWER],
        ];
    }

    public function test_viewer_is_forbidden_before_validation_runs(): void
    {
        [$workspace] = $this->workspaceWithOwner();
        $viewer = $this->memberOf($workspace, WorkspaceMember::ROLE_VIEWER);

        $this->actingInWorkspace($viewer, $workspace)
            ->post(route('utm-templates.store'), [])
            ->assertForbidden();
    }

    public function test_templates_of_another_workspace_cannot_be_changed(): void
    {
        [$workspace, $owner] = $this->workspaceWithOwner();
        $other = $this->workspaceFor(User::factory()->create(), 'Other');
        $foreign = $this->template($other, 'Foreign', ['utm_source' => 'foreign']);

        $this->actingInWorkspace($owner, $workspace)
            ->patch(route('utm-templates.update', $foreign), ['name' => 'Mine', 'utm_source' => 'mine'])
            ->assertForbidden();

        $this->actingInWorkspace($owner, $workspace)
            ->delete(route('utm-templates.destroy', $foreign))
            ->assertForbidden();

        $this->assertDatabaseHas('utm_templates', ['id' => $foreign->id, 'name' => 'Foreign', 'utm_source' => 'foreign']);
    }

    public function test_a_manager_of_both_workspaces_still_needs_the_template_workspace_selected(): void
    {
        [$workspace, $owner] = $this->workspaceWithOwner();
        $other = $this->workspaceFor($owner, 'Second');
        $template = $this->template($other, 'Second only', ['utm_source' => 'second']);

        $this->actingInWorkspace($owner, $workspace)
            ->delete(route('utm-templates.destroy', $template))
            ->assertForbidden();

        $this->actingInWorkspace($owner, $other)
            ->delete(route('utm-templates.destroy', $template))
            ->assertRedirect();

        $this->assertDatabaseCount('utm_templates', 0);
    }

    public function test_templates_are_listed_only_in_their_own_workspace(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $editor = $this->memberOf($workspace, WorkspaceMember::ROLE_EDITOR);
        $this->template($workspace, 'Social', ['utm_source' => 'social']);
        $this->template($workspace, 'Email', ['utm_medium' => 'email']);
        $this->template($this->workspaceFor(User::factory()->create(), 'Other'), 'Foreign', ['utm_source' => 'foreign']);

        $this->actingInWorkspace($editor, $workspace)
            ->get(route('links.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('utmTemplates', 2)
                ->where('utmTemplates.0.name', 'Email')
                ->where('utmTemplates.0.utm_medium', 'email')
                ->where('utmTemplates.0.utm_source', null)
                ->missing('utmTemplates.0.workspace_id')
                ->where('utmTemplates.1.name', 'Social'));

        $this->actingInWorkspace($owner, $workspace)
            ->get(route('settings.workspace'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('utmTemplates', 2)
                ->where('utmTemplates.1.utm_source', 'social'));
    }

    public function test_template_names_are_unique_within_a_workspace_only(): void
    {
        [$workspace, $owner] = $this->workspaceWithOwner();
        $this->template($workspace, 'Newsletter', ['utm_source' => 'newsletter']);
        $other = $this->workspaceFor($owner, 'Second');
        $existing = $this->template($other, 'Social', ['utm_source' => 'social']);

        $this->actingInWorkspace($owner, $workspace)
            ->post(route('utm-templates.store'), ['name' => 'Newsletter', 'utm_source' => 'again'])
            ->assertSessionHasErrors('name');

        $this->actingInWorkspace($owner, $other)
            ->post(route('utm-templates.store'), ['name' => 'Newsletter', 'utm_source' => 'newsletter'])
            ->assertSessionHasNoErrors();

        $this->actingInWorkspace($owner, $other)
            ->patch(route('utm-templates.update', $existing), ['name' => 'Social', 'utm_source' => 'updated'])
            ->assertSessionHasNoErrors();

        $this->actingInWorkspace($owner, $other)
            ->patch(route('utm-templates.update', $existing), ['name' => 'Newsletter', 'utm_source' => 'updated'])
            ->assertSessionHasErrors('name');
    }

    public function test_template_input_is_validated(): void
    {
        [$workspace, $owner] = $this->workspaceWithOwner();

        $this->actingInWorkspace($owner, $workspace)
            ->post(route('utm-templates.store'), ['name' => '', 'utm_source' => ''])
            ->assertSessionHasErrors(['name', 'parameters']);

        $this->actingInWorkspace($owner, $workspace)
            ->post(route('utm-templates.store'), [
                'name' => str_repeat('n', 121),
                'utm_campaign' => str_repeat('c', 251),
                'utm_term' => ['array'],
            ])
            ->assertSessionHasErrors(['name', 'utm_campaign', 'utm_term']);

        $this->assertDatabaseCount('utm_templates', 0);
    }

    public function test_deleting_a_workspace_deletes_its_templates(): void
    {
        [$workspace, $owner] = $this->workspaceWithOwner();
        $this->workspaceFor($owner, 'Second');
        $this->template($workspace, 'Newsletter', ['utm_source' => 'newsletter']);

        $this->actingInWorkspace($owner, $workspace)
            ->delete(route('workspaces.destroy', $workspace))
            ->assertRedirect();

        $this->assertDatabaseCount('utm_templates', 0);
    }

    private function template(Workspace $workspace, string $name, array $values): UtmTemplate
    {
        return UtmTemplate::create(['workspace_id' => $workspace->id, 'name' => $name, ...$values]);
    }
}
