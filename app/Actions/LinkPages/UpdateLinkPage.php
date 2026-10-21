<?php

namespace App\Actions\LinkPages;

use App\Models\LinkPage;
use App\Services\LinkPages\LinkPageLogoStorage;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UpdateLinkPage
{
    public function __construct(
        private readonly Gate $gate,
        private readonly LinkPagePaths $paths,
        private readonly LinkPageLogoStorage $logos,
    ) {}

    public function handle(Request $request, LinkPage $linkPage, array $data): LinkPage
    {
        $this->gate->forUser($request->user())->authorize('update', $linkPage);

        $linkPage->fill([
            'name' => $data['name'],
            'path' => $this->paths->ensureAvailable($data['path'], $linkPage),
            'title' => $data['title'],
            'description' => filled($data['description'] ?? null) ? $data['description'] : null,
        ]);

        return DB::transaction(function () use ($request, $linkPage, $data): LinkPage {
            $this->replaceItems($linkPage, $data['items'] ?? []);

            return $this->logos->save(
                $linkPage,
                $request->hasFile('logo') ? $request->file('logo') : null,
                (bool) ($data['remove_logo'] ?? false),
            );
        });
    }

    private function replaceItems(LinkPage $linkPage, array $items): void
    {
        $linkPage->items()->delete();

        foreach (array_values($items) as $position => $item) {
            $linkPage->items()->create([
                'short_link_id' => (int) $item['short_link_id'],
                'label' => filled($item['label'] ?? null) ? trim($item['label']) : null,
                'position' => $position,
            ]);
        }
    }
}
