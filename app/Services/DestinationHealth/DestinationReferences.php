<?php

namespace App\Services\DestinationHealth;

use App\Models\RoutingRule;
use App\Models\ShortLink;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DestinationReferences
{
    public const RELATIONS = ['routingRules.variants'];

    public function forShortLink(ShortLink $shortLink): array
    {
        $shortLink->loadMissing(self::RELATIONS);

        $references = [['url' => $shortLink->destination_url, 'role' => 'destination', 'label' => 'Default destination']];

        if (filled($shortLink->fallback_url)) {
            $references[] = ['url' => $shortLink->fallback_url, 'role' => 'fallback', 'label' => 'Fallback URL'];
        }

        foreach ($shortLink->routingRules->where('is_enabled', true) as $rule) {
            if ($rule->type === RoutingRule::TYPE_SPLIT_TEST) {
                foreach ($rule->variants->where('is_enabled', true) as $variant) {
                    $references[] = ['url' => $variant->destination_url, 'role' => 'routing_variant', 'label' => $rule->name.' · '.$variant->name];
                }
            } elseif (filled($rule->destination_url)) {
                $references[] = ['url' => $rule->destination_url, 'role' => 'routing_rule', 'label' => $rule->name];
            }
        }

        return array_values(array_filter($references, fn (array $reference) => filled($reference['url'])));
    }

    public function urlsFor(ShortLink $shortLink): array
    {
        return array_values(array_unique(array_column($this->forShortLink($shortLink), 'url')));
    }

    public function linksReferencing(Workspace $workspace, array $urls): Collection
    {
        if ($urls === []) {
            return collect();
        }

        return $workspace->shortLinks()
            ->whereNull('archived_at')
            ->where(fn (Builder $query) => $query
                ->whereIn('destination_url', $urls)
                ->orWhereIn('fallback_url', $urls)
                ->orWhereHas('routingRules', fn (Builder $rules) => $rules
                    ->where('is_enabled', true)
                    ->where(fn (Builder $rules) => $rules
                        ->whereIn('destination_url', $urls)
                        ->orWhereHas('variants', fn (Builder $variants) => $variants
                            ->where('is_enabled', true)
                            ->whereIn('destination_url', $urls)))))
            ->with([...self::RELATIONS, 'domain', 'qrCodes:id,short_link_id,name'])
            ->get();
    }
}
