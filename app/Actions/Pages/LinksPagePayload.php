<?php

namespace App\Actions\Pages;

use App\Actions\Workspaces\WorkspacePayloads;
use App\Actions\Workspaces\WorkspaceViewFactory;
use App\Models\User;
use App\Models\Workspace;
use App\Services\ShortLinks\SmartRouting;

class LinksPagePayload
{
    public function __construct(
        private readonly WorkspaceShellPayload $shell,
        private readonly WorkspacePayloads $workspacePayloads,
        private readonly WorkspaceViewFactory $views,
        private readonly SmartRouting $routing,
    ) {}

    /** @return array<string, mixed> */
    public function handle(Workspace $workspace, User $user): array
    {
        $view = $this->views->make($workspace, $user);

        return [
            ...$this->shell->handle($workspace, $user),
            'domains' => $this->workspacePayloads->domains($workspace),
            'folders' => $this->workspacePayloads->folders($view),
            'tags' => $workspace->tags()->orderBy('name')->get(),
            'links' => $this->workspacePayloads->links($view),
            'routingSchema' => $this->routing->editorPayload(),
        ];
    }
}
