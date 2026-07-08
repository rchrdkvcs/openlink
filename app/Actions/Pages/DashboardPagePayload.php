<?php

namespace App\Actions\Pages;

use App\Actions\Workspaces\WorkspacePayloads;
use App\Actions\Workspaces\WorkspaceViewFactory;
use App\Models\User;
use App\Models\Workspace;

class DashboardPagePayload
{
    public function __construct(
        private readonly WorkspaceShellPayload $shell,
        private readonly WorkspacePayloads $workspacePayloads,
        private readonly WorkspaceViewFactory $views,
    ) {}

    /** @return array<string, mixed> */
    public function handle(Workspace $workspace, User $user): array
    {
        $view = $this->views->make($workspace, $user);

        return [
            ...$this->shell->handle($workspace, $user),
            'domains' => $this->workspacePayloads->domains($workspace),
            'links' => $this->workspacePayloads->links($view),
        ];
    }
}
