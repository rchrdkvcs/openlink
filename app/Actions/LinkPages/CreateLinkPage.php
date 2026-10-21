<?php

namespace App\Actions\LinkPages;

use App\Actions\Workspaces\CurrentWorkspace;
use App\Models\LinkPage;

class CreateLinkPage
{
    public function __construct(
        private readonly CurrentWorkspace $current,
        private readonly LinkPagePaths $paths,
    ) {}

    public function handle(array $data): LinkPage
    {
        $workspace = $this->current->require('editContent');

        return $workspace->linkPages()->create([
            'name' => $data['name'],
            'path' => $this->paths->ensureAvailable($data['path']),
            'title' => $data['name'],
        ]);
    }
}
