<?php

namespace App\Actions\LinkPages;

use App\Models\LinkPage;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\Request;

class PublishLinkPage
{
    public function __construct(private readonly Gate $gate) {}

    public function publish(Request $request, LinkPage $linkPage): LinkPage
    {
        return $this->changePublication($request, $linkPage, $linkPage->published_at ?? now());
    }

    public function unpublish(Request $request, LinkPage $linkPage): LinkPage
    {
        return $this->changePublication($request, $linkPage, null);
    }

    private function changePublication(Request $request, LinkPage $linkPage, ?CarbonInterface $publishedAt): LinkPage
    {
        $this->gate->forUser($request->user())->authorize('update', $linkPage);

        $linkPage->forceFill(['published_at' => $publishedAt])->save();

        return $linkPage;
    }
}
