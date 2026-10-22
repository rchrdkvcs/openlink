<?php

namespace App\Services\Analytics\Report;

use App\Models\AnalyticsEvent;
use App\Models\ShortLink;
use App\Models\Workspace;
use App\Services\Analytics\AnalyticsFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AnalyticsEventSlice
{
    private const INSTANT = 'Y-m-d H:i:s.u';

    public function __construct(
        private readonly Workspace $workspace,
        private readonly AnalyticsFilters $filters,
    ) {}

    public function filters(): AnalyticsFilters
    {
        return $this->filters;
    }

    public function query(): Builder
    {
        $timezone = config('app.timezone');
        $filters = $this->filters;

        return AnalyticsEvent::query()
            ->where('workspace_id', $this->workspace->id)
            ->where('occurred_at', '>=', $filters->from->setTimezone($timezone)->format(self::INSTANT))
            ->where('occurred_at', '<', $filters->to->setTimezone($timezone)->format(self::INSTANT))
            ->when($filters->unavailable !== [], fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when($filters->shortLinkId !== null, fn (Builder $query) => $query->where('short_link_id', $filters->shortLinkId))
            ->when($filters->qrCodeId !== null, fn (Builder $query) => $query->where('qr_code_id', $filters->qrCodeId))
            ->when($filters->domainId !== null, fn (Builder $query) => $query->where('domain_id', $filters->domainId))
            ->when($filters->routingRuleId !== null, fn (Builder $query) => $query->where('routing_rule_id', $filters->routingRuleId))
            ->when($filters->routingVariantId !== null, fn (Builder $query) => $query->where('routing_variant_id', $filters->routingVariantId))
            ->when($filters->metric !== null, fn (Builder $query) => $query->where('metric', $filters->metric))
            ->when($filters->folderId !== null, fn (Builder $query) => $query->whereIn(
                'short_link_id',
                ShortLink::query()->where('workspace_id', $this->workspace->id)->where('folder_id', $filters->folderId)->select('id'),
            ))
            ->when($filters->tagId !== null, fn (Builder $query) => $query->whereIn(
                'short_link_id',
                DB::table('short_link_tag')->where('tag_id', $filters->tagId)->select('short_link_id'),
            ));
    }

    public function ordered(): Builder
    {
        return $this->query()->orderBy('occurred_at');
    }
}
