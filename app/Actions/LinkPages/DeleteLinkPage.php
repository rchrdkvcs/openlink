<?php

namespace App\Actions\LinkPages;

use App\Models\LinkPage;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\Request;
use RuntimeException;

class DeleteLinkPage
{
    public function __construct(private readonly Gate $gate) {}

    public function handle(Request $request, LinkPage $linkPage): void
    {
        $this->gate->forUser($request->user())->authorize('delete', $linkPage);

        if (! $linkPage->delete()) {
            throw new RuntimeException('Unable to delete the Link Page.');
        }
    }
}
