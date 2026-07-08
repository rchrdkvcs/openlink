<?php

namespace App\Actions\Pages;

use App\Actions\Workspaces\WorkspacePayloads;
use App\Models\User;
use App\Models\Workspace;

class DomainsPagePayload
{
    public function __construct(
        private readonly WorkspaceShellPayload $shell,
        private readonly WorkspacePayloads $workspacePayloads,
    ) {}

    /** @return array<string, mixed> */
    public function handle(Workspace $workspace, User $user): array
    {
        return [
            ...$this->shell->handle($workspace, $user),
            'domains' => $this->workspacePayloads->domains($workspace),
        ];
    }
}
