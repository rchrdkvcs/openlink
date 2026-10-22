<?php

namespace App\Services\LinkPreviews;

use App\Models\Domain;
use App\Models\ShortLink;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use WeakMap;

class LinkPreviewImageCleanup
{
    private const OWNERS = [ShortLink::class, Workspace::class, Domain::class, User::class];

    private WeakMap $pending;

    public function __construct(private readonly LinkPreviewImageStorage $images)
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
        $paths = $this->shortLinksOf($owner)->whereNotNull('preview_image_path')->pluck('preview_image_path')->all();

        if ($paths !== []) {
            $this->pending[$owner] = $paths;
        }
    }

    public function purge(Model $owner): void
    {
        $paths = $this->pending[$owner] ?? [];
        unset($this->pending[$owner]);

        if ($paths !== []) {
            DB::afterCommit(fn () => $this->images->deleteUnreferenced($paths));
        }
    }

    private function shortLinksOf(Model $owner): Builder
    {
        $shortLinks = ShortLink::query();

        return match (true) {
            $owner instanceof ShortLink => $shortLinks->whereKey($owner->getKey()),
            $owner instanceof Workspace => $shortLinks->where('workspace_id', $owner->getKey()),
            $owner instanceof Domain => $shortLinks->where('domain_id', $owner->getKey()),
            default => $shortLinks->whereIn('workspace_id', Workspace::query()->select('id')->where('owner_id', $owner->getKey())),
        };
    }
}
