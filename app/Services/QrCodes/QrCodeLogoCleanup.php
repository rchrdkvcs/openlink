<?php

namespace App\Services\QrCodes;

use App\Models\Domain;
use App\Models\QrCode;
use App\Models\ShortLink;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use WeakMap;

class QrCodeLogoCleanup
{
    private const OWNERS = [QrCode::class, ShortLink::class, Workspace::class, Domain::class, User::class];

    private WeakMap $pending;

    public function __construct(private readonly QrCodeLogoStorage $logos)
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
        $paths = $this->logos->pathsOf($this->qrCodesOf($owner));

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

    private function qrCodesOf(Model $owner): Builder
    {
        $qrCodes = QrCode::query();

        return match (true) {
            $owner instanceof QrCode => $qrCodes->whereKey($owner->getKey()),
            $owner instanceof ShortLink => $qrCodes->where('short_link_id', $owner->getKey()),
            $owner instanceof Workspace => $qrCodes->where('workspace_id', $owner->getKey()),
            $owner instanceof Domain => $qrCodes->whereIn('short_link_id', ShortLink::query()->select('id')->where('domain_id', $owner->getKey())),
            default => $qrCodes->whereIn('workspace_id', Workspace::query()->select('id')->where('owner_id', $owner->getKey())),
        };
    }
}
