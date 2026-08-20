<?php

namespace App\Actions\BioPages;

use App\Models\BioPage;
use App\Models\Domain;
use App\Models\ShortLink;
use App\Services\BioPages\BioTheme;
use Illuminate\Support\Facades\Storage;

class BioPagePayload
{
    public function __construct(private readonly BioTheme $themes) {}

    /** @return array<string, mixed> */
    public function summary(BioPage $bioPage): array
    {
        $bioPage->loadMissing('draftDomain', 'publishedDomain', 'elements');

        return [
            'id' => $bioPage->id,
            'displayName' => $bioPage->draft['displayName'] ?? '',
            'bioUrl' => $this->bioUrl($bioPage),
            'status' => $this->status($bioPage),
            'hasUnpublishedChanges' => $this->hasUnpublishedChanges($bioPage),
            'updatedAt' => $bioPage->updated_at->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function editor(BioPage $bioPage): array
    {
        $bioPage->loadMissing('draftDomain', 'publishedDomain', 'elements');

        return [
            'id' => $bioPage->id,
            'draft' => $this->version($bioPage, false),
            'published' => $bioPage->published ? $this->version($bioPage, true) : null,
            'publishedAt' => $bioPage->published_at?->toIso8601String(),
            'bioUrl' => $this->bioUrl($bioPage),
            'status' => $this->status($bioPage),
            'hasUnpublishedChanges' => $this->hasUnpublishedChanges($bioPage),
        ];
    }

    /** @return array<string, mixed> */
    public function published(BioPage $bioPage): array
    {
        $version = $this->version($bioPage, true);
        $version['elements'] = collect($version['elements'])
            ->filter(fn (array $element) => $element['visible'] ?? false)
            ->map(function (array $element) use ($bioPage): array {
                if (in_array($element['type'] ?? null, ['destination', 'social'], true)) {
                    $element['url'] = route('public.bio.activate', [$bioPage, $element['id']], false);
                }

                return $element;
            })
            ->values()
            ->all();

        return $version;
    }

    /** @return array<string, mixed> */
    public function domain(Domain $domain): array
    {
        return [
            'id' => $domain->id,
            'hostname' => $domain->hostname,
            'status' => $domain->status,
            'isDefault' => $domain->is_default,
        ];
    }

    /** @return array<string, mixed> */
    public function shortLink(ShortLink $shortLink): array
    {
        $shortLink->loadMissing('domain');

        return [
            'id' => $shortLink->id,
            'slug' => $shortLink->slug,
            'shortUrl' => 'https://'.$shortLink->domain->hostname.'/'.$shortLink->slug,
            'destinationUrl' => $shortLink->destination_url,
            'status' => $shortLink->is_enabled && ! $shortLink->archived_at ? 'active' : 'unavailable',
        ];
    }

    /** @return array<string, mixed> */
    private function version(BioPage $bioPage, bool $published): array
    {
        $version = $published ? ($bioPage->published ?? []) : $bioPage->draft;
        $elements = $bioPage->elements
            ->filter(fn ($element) => ($published ? $element->published : $element->draft) !== null)
            ->sortBy($published ? 'published_position' : 'position')
            ->map(function ($element) use ($published): array {
                $data = $published ? $element->published : $element->draft;

                return array_merge($data, ['id' => $element->id]);
            })
            ->values()
            ->all();

        $domainId = $published ? $bioPage->published_domain_id : $bioPage->draft_domain_id;
        $slug = $published ? $bioPage->published_slug : $bioPage->draft_slug;

        return [
            'domainId' => $domainId,
            'slug' => $slug,
            'displayName' => $version['displayName'] ?? '',
            'publicHandle' => $version['publicHandle'] ?? '',
            'biography' => $version['biography'] ?? '',
            'profileImageUrl' => $this->mediaUrl($version['profileImagePath'] ?? null),
            'backgroundImageUrl' => $this->mediaUrl($version['backgroundImagePath'] ?? null),
            'elements' => $elements,
            'theme' => $this->themes->withDefaults($version['theme'] ?? []),
            'shareTitle' => $version['shareTitle'] ?? '',
            'shareDescription' => $version['shareDescription'] ?? '',
            'isIndexable' => $version['isIndexable'] ?? true,
            'showBranding' => $version['showBranding'] ?? true,
        ];
    }

    private function bioUrl(BioPage $bioPage): string
    {
        return 'https://'.$bioPage->draftDomain->hostname.'/'.$bioPage->draft_slug;
    }

    private function status(BioPage $bioPage): string
    {
        if (! $bioPage->isPublished()) {
            return 'draft';
        }

        return $bioPage->publishedDomain?->isUsable() ? 'published' : 'unavailable';
    }

    private function hasUnpublishedChanges(BioPage $bioPage): bool
    {
        if (! $bioPage->published) {
            return true;
        }

        if ($bioPage->draft_domain_id !== $bioPage->published_domain_id
            || $bioPage->draft_slug !== $bioPage->published_slug
            || $bioPage->draft !== $bioPage->published) {
            return true;
        }

        return $bioPage->elements->contains(fn ($element) => $element->draft !== $element->published
            || $element->position !== $element->published_position);
    }

    private function mediaUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }
}
