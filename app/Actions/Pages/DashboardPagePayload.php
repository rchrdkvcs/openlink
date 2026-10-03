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

    public function handle(Workspace $workspace, User $user): array
    {
        $view = $this->views->make($workspace, $user);

        return [
            ...$this->shell->handle($workspace, $user),
            'domains' => $this->workspacePayloads->domains($workspace),
            'folders' => $this->workspacePayloads->folders($view),
            'linkCounts' => $this->workspacePayloads->linkCounts($workspace),
            'recentLinks' => collect($this->workspacePayloads->linksPage($view)->items())->take(6)->values(),
        ];
    }
}
