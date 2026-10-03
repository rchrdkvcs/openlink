<?php

namespace App\Http\Requests\ShortLinks;

use App\Actions\Workspaces\CurrentWorkspace;
use App\Models\Workspace;

class StoreShortLinkRequest extends ShortLinkRequest
{
    public function authorize(): bool
    {
        $workspace = $this->workspace();

        return $workspace !== null && $this->user()->can('editContent', $workspace);
    }

    public function workspace(): ?Workspace
    {
        return $this->container->make(CurrentWorkspace::class)->get();
    }

    protected function creating(): bool
    {
        return true;
    }
}
