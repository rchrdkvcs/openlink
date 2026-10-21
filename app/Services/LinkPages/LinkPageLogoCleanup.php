<?php

namespace App\Services\LinkPages;

use App\Models\LinkPage;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use WeakMap;

class LinkPageLogoCleanup
{
    private const OWNERS = [LinkPage::class, Workspace::class, User::class];

    private WeakMap $pending;

    public function __construct(private readonly LinkPageLogoStorage $logos)
    {
        $this->pending = new WeakMap;
    }

    public function subscribe(Dispatcher $events): void
    {
        foreach (self::OWNERS as $owner) {
            $events->listen('eloquent.deleting: '.$owner, $this->collect(...));
            $events->listen('eloquent.deleted: '.$owner, $this->purge(...));
        }
    }

    public function collect(Model $owner): void
    {
        $paths = $this->logos->pathsOf($this->linkPagesOf($owner));

        if ($paths !== []) {
            $this->pending[$owner] = $paths;
        }
    }

    public function purge(Model $owner): void
    {
        $paths = $this->pending[$owner] ?? [];
        unset($this->pending[$owner]);

        if ($paths !== []) {
            DB::afterCommit(fn () => $this->logos->delete($paths));
        }
    }

    private function linkPagesOf(Model $owner): Builder
    {
        $linkPages = LinkPage::query();

        return match (true) {
            $owner instanceof LinkPage => $linkPages->whereKey($owner->getKey()),
            $owner instanceof Workspace => $linkPages->where('workspace_id', $owner->getKey()),
            default => $linkPages->whereIn('workspace_id', Workspace::query()->select('id')->where('owner_id', $owner->getKey())),
        };
    }
}
