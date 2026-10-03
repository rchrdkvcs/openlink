<?php

namespace App\Http\Requests\ShortLinks;

use App\Models\ShortLink;
use App\Models\Workspace;

class UpdateShortLinkRequest extends ShortLinkRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->shortLink());
    }

    public function shortLink(): ShortLink
    {
        return $this->route('shortLink');
    }

    public function workspace(): ?Workspace
    {
        return $this->shortLink()->workspace;
    }

    protected function creating(): bool
    {
        return false;
    }
}
