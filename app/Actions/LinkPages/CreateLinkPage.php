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

        $path = $this->paths->ensureAvailable($data['path']);

        return $this->paths->claim(fn (): LinkPage => $workspace->linkPages()->create([
            'name' => $data['name'],
            'path' => $path,
            'title' => $data['name'],
        ]));
    }
}
