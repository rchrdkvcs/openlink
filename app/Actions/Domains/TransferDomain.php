<?php

namespace App\Actions\Domains;

use App\Models\Domain;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Validation\ValidationException;

class TransferDomain
{
    public function __construct(private readonly Gate $gate) {}

    public function handle(User $actor, Domain $domain, int $targetWorkspaceId): Domain
    {
        $gate = $this->gate->forUser($actor);
        $gate->authorize('manage', $domain);
        abort_if($domain->is_default, 403);

        $workspace = $domain->workspace;
        $targetWorkspace = Workspace::query()->find($targetWorkspaceId);

        abort_unless($targetWorkspace && $gate->allows('manage', $targetWorkspace), 403);

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

        $domain->workspace()->associate($targetWorkspace)->save();

        return $domain;
    }
}
