<?php

namespace App\Actions\Domains;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\Domain;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TransferDomain
{
    public function __construct(private readonly WorkspaceAccess $access) {}

    public function handle(Request $request, Domain $domain, int $targetWorkspaceId): Domain
    {
        $workspace = $this->access->requireManagedDomain($request, $domain);
        abort_if($domain->is_default, 403);

        $targetWorkspace = $request->user()
            ->workspaces()
            ->where('workspaces.id', $targetWorkspaceId)
            ->first();

        abort_unless($targetWorkspace && $this->access->canManageWorkspace($request->user(), $targetWorkspace), 403);

        if ($targetWorkspace->id === $workspace->id) {
            return $domain;
        }

        if ($domain->shortLinks()->exists()) {
            throw ValidationException::withMessages([
                'workspace_id' => __('openlink.validation.domain_has_links'),
            ]);
        }

        if ((int) $workspace->preferred_domain_id === $domain->id) {
            $workspace->forceFill(['preferred_domain_id' => null])->save();
        }

        $domain->update(['workspace_id' => $targetWorkspace->id]);

        return $domain;
    }
}
