<?php

namespace App\Actions\Folders;

use App\Actions\ShortLinks\ShortLinkMutation;
use App\Models\Folder;
use App\Models\ShortLink;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteFolder
{
    public function __construct(private readonly ShortLinkMutation $shortLinks) {}

    public function handle(User $user, Folder $folder): void
    {
        DB::transaction(function () use ($user, $folder): void {
            ShortLink::query()
                ->where('folder_id', $folder->id)
                ->orderBy('id')
                ->get()
                ->each(fn (ShortLink $shortLink) => $this->shortLinks->move($user, $shortLink, null));

            $folder->delete();
        });
    }
}
