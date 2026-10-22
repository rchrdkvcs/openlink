<?php

namespace App\Services\Analytics;

use App\Models\Domain;
use App\Models\RoutingRule;
use App\Models\RoutingVariant;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;

class AnalyticsFilterTargets
{
    public function resolve(Workspace $workspace, AnalyticsFilters $filters): AnalyticsFilters
    {
        return $filters->withUnavailable($this->unavailable($workspace, $filters));
    }

    public function unavailable(Workspace $workspace, AnalyticsFilters $filters): array
    {
        return array_keys(array_filter(
            $filters->targets(),
            fn (int $id, string $key) => ! $this->query($workspace, $key)->whereKey($id)->exists(),
            ARRAY_FILTER_USE_BOTH,
        ));
    }

    private function query(Workspace $workspace, string $key): Builder
    {
        return match ($key) {
            'link' => $workspace->shortLinks()->getQuery(),
            'qr' => $workspace->qrCodes()->getQuery(),
            'domain' => Domain::query()->where(fn (Builder $query) => $query
                ->where('workspace_id', $workspace->id)
                ->orWhere('is_default', true)),
            'folder' => $workspace->folders()->getQuery(),
            'tag' => $workspace->tags()->getQuery(),
            'rule' => RoutingRule::query()->whereHas('shortLink', fn (Builder $query) => $query->where('workspace_id', $workspace->id)),
            'variant' => RoutingVariant::query()->whereHas('rule.shortLink', fn (Builder $query) => $query->where('workspace_id', $workspace->id)),
        };
    }
}
