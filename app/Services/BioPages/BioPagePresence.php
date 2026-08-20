<?php

namespace App\Services\BioPages;

use App\Models\BioPage;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class BioPagePresence
{
    private const TTL_SECONDS = 90;

    /** @return list<string> */
    public function touch(BioPage $bioPage, User $user): array
    {
        $now = now()->timestamp;
        $editors = $this->recent($bioPage, $now)
            ->put($user->id, ['name' => $user->name, 'seenAt' => $now]);

        Cache::put($this->key($bioPage), $editors->all(), self::TTL_SECONDS);

        return $editors
            ->except($user->id)
            ->pluck('name')
            ->sort()
            ->values()
            ->all();
    }

    private function recent(BioPage $bioPage, int $now): Collection
    {
        return collect(Cache::get($this->key($bioPage), []))
            ->filter(fn (array $editor) => $editor['seenAt'] >= $now - self::TTL_SECONDS);
    }

    private function key(BioPage $bioPage): string
    {
        return 'bio-page-presence:'.$bioPage->id;
    }
}
