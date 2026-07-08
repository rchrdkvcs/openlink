<?php

namespace App\Actions\Pages;

use App\Actions\Workspaces\WorkspacePayloads;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Routing\SmartRoutingSchema;

class LinksPagePayload
{
    public function __construct(
        private readonly WorkspaceShellPayload $shell,
        private readonly WorkspacePayloads $workspacePayloads,
        private readonly SmartRoutingSchema $routingSchema,
    ) {}

    /** @return array<string, mixed> */
    public function handle(Workspace $workspace, User $user): array
    {
        return [
            ...$this->shell->handle($workspace, $user),
            'domains' => $this->workspacePayloads->domains($workspace),
            'folders' => $this->workspacePayloads->folders($workspace, $user),
            'tags' => $workspace->tags()->orderBy('name')->get(),
            'links' => $this->workspacePayloads->links($workspace, $user),
            'routingSchema' => $this->routingSchema->editorPayload(),
        ];
    }
}
