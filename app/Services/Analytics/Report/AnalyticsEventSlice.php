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
    /** @param list<int>|null $accessibleLinkIds */
    public function __construct(
        private readonly Workspace $workspace,
        private readonly AnalyticsFilters $filters,
        private readonly ?array $accessibleLinkIds = null,
    ) {}

    public function filters(): AnalyticsFilters
    {
        return $this->filters;
    }

    public function query(): Builder
    {
        return AnalyticsEvent::query()
            ->where('workspace_id', $this->workspace->id)
            ->whereBetween('occurred_at', [$this->filters->from, $this->filters->to])
            ->when($this->accessibleLinkIds !== null, fn (Builder $query) => $query->whereIn('short_link_id', $this->accessibleLinkIds))
            ->when($this->filters->shortLinkId, fn (Builder $query, int $id) => $query->where('short_link_id', $id))
            ->when($this->filters->qrCodeId, fn (Builder $query, int $id) => $query->where('qr_code_id', $id))
            ->when($this->filters->bioPageId, fn (Builder $query, int $id) => $query->where('bio_page_id', $id))
            ->when($this->filters->domainId, fn (Builder $query, int $id) => $query->where('domain_id', $id))
            ->when($this->filters->routingRuleId, fn (Builder $query, int $id) => $query->where('routing_rule_id', $id))
            ->when($this->filters->routingVariantId, fn (Builder $query, int $id) => $query->where('routing_variant_id', $id))
            ->when($this->filters->metric, fn (Builder $query, string $metric) => $query->where('metric', $metric))
            ->when($this->filters->folderId, fn (Builder $query, int $id) => $query->whereIn(
                'short_link_id',
                ShortLink::query()->where('workspace_id', $this->workspace->id)->where('folder_id', $id)->select('id'),
            ))
            ->when($this->filters->tagId, fn (Builder $query, int $id) => $query->whereIn(
                'short_link_id',
                DB::table('short_link_tag')->where('tag_id', $id)->select('short_link_id'),
            ));
    }

    public function ordered(): Builder
    {
        return $this->query()->orderBy('occurred_at');
    }
}
