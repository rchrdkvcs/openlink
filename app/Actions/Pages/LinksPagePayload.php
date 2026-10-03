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

    public function handle(Workspace $workspace, User $user, array $filters = []): array
    {
        $view = $this->views->make($workspace, $user);
        $page = $this->workspacePayloads->linksPage($view, $filters);

        return [
            ...$this->shell->handle($workspace, $user),
            'domains' => $this->workspacePayloads->domains($workspace),
            'folders' => $this->workspacePayloads->folders($view),
            'tags' => $workspace->tags()->orderBy('name')->get(),
            'links' => $page->items(),
            'linksPagination' => [
                'currentPage' => $page->currentPage(),
                'lastPage' => $page->lastPage(),
                'total' => $page->total(),
                'perPage' => $page->perPage(),
            ],
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? '',
                'tag' => $filters['tag'] ?? '',
                'folder' => (string) ($filters['folder'] ?? ''),
            ],
            'routingSchema' => $this->routing->editorPayload(),
        ];
    }
}
