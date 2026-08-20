<?php

namespace App\Services;

use App\Models\BioPage;
use App\Models\PublicSlug;
use App\Models\ShortLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublicSlugRegistry
{
    public function syncShortLink(ShortLink $shortLink): void
    {
        $this->sync(PublicSlug::TYPE_SHORT_LINK, $shortLink->id, [
            [$shortLink->domain_id, $shortLink->slug],
        ]);
    }

    public function syncBioPage(BioPage $bioPage): void
    {
        $addresses = [[$bioPage->draft_domain_id, $bioPage->draft_slug]];

        if ($bioPage->published_domain_id && filled($bioPage->published_slug)) {
            $addresses[] = [$bioPage->published_domain_id, $bioPage->published_slug];
        }

        $this->sync(PublicSlug::TYPE_BIO_PAGE, $bioPage->id, $addresses);
    }

    public function forget(string $type, int $resourceId): void
    {
        PublicSlug::query()->where($this->column($type), $resourceId)->delete();
    }

    /** @param list<array{int, string}> $addresses */
    private function sync(string $type, int $resourceId, array $addresses): void
    {
        $addresses = collect($addresses)
            ->map(fn (array $address) => [(int) $address[0], trim((string) $address[1], '/')])
            ->unique(fn (array $address) => $address[0].':'.$address[1])
            ->values();

        DB::transaction(function () use ($type, $resourceId, $addresses): void {
            $column = $this->column($type);
            foreach ($addresses as [$domainId, $slug]) {
                $reservation = PublicSlug::query()->firstOrCreate(
                    ['domain_id' => $domainId, 'slug' => $slug],
                    [$column => $resourceId],
                );

                if ($reservation->{$column} !== $resourceId) {
                    throw ValidationException::withMessages([
                        'slug' => __('openlink.validation.slug_duplicate'),
                    ]);
                }
            }

            $keepIds = PublicSlug::query()
                ->where($column, $resourceId)
                ->get()
                ->filter(fn (PublicSlug $reservation) => $addresses->contains(
                    fn (array $address) => $reservation->domain_id === $address[0] && $reservation->slug === $address[1]
                ))
                ->pluck('id');

            PublicSlug::query()
                ->where($column, $resourceId)
                ->when($keepIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $keepIds))
                ->delete();
        });
    }

    private function column(string $type): string
    {
        return $type === PublicSlug::TYPE_BIO_PAGE ? 'bio_page_id' : 'short_link_id';
    }
}
