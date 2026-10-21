<?php

namespace App\Services\Analytics\Report;

use App\Models\ConversionAttribution;
use App\Models\RoutingRule;
use App\Models\RoutingVariant;
use App\Models\ShortLink;
use App\Models\Workspace;
use App\Services\Analytics\AnalyticsFilters;
use App\Services\Conversions\AttributionTokens;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ConversionSection
{
    public const MINIMUM_ATTRIBUTED_VISITS = 30;

    public function build(Workspace $workspace, AnalyticsFilters $filters): array
    {
        $query = fn (): Builder => $this->attributions($workspace, $filters);

        return [
            'enabled' => $workspace->shortLinks()->whereNotNull('conversion_goal')->exists() || $query()->exists(),
            'window_hours' => AttributionTokens::WINDOW_HOURS,
            'minimum_attributed_visits' => self::MINIMUM_ATTRIBUTED_VISITS,
            'totals' => $this->measures($this->counted($query())->first()),
            'links' => $this->links($query()),
            'routing' => $this->routing($query()),
        ];
    }

    private function links(Builder $query): array
    {
        $rows = $this->counted($query)
            ->addSelect('short_link_id')
            ->groupBy('short_link_id')
            ->orderByDesc('attributed_visits')
            ->limit(10)
            ->get();

        $links = ShortLink::query()->with('domain')->findMany($rows->pluck('short_link_id'))->keyBy('id');

        return $rows->map(function ($row) use ($links) {
            $link = $links->get($row->short_link_id);

            return [
                'id' => (int) $row->short_link_id,
                'slug' => $link?->slug,
                'short_url' => $link?->domain ? $link->shortUrl() : null,
                'conversion_goal' => $link?->conversion_goal,
                ...$this->measures($row),
                'insufficient_evidence' => $this->insufficient($row),
            ];
        })->all();
    }

    private function routing(Builder $query): array
    {
        $rows = $this->counted($query)
            ->addSelect('short_link_id', 'routing_rule_id', 'routing_variant_id')
            ->groupBy('short_link_id', 'routing_rule_id', 'routing_variant_id')
            ->orderByDesc('attributed_visits')
            ->limit(20)
            ->get();

        $links = ShortLink::query()->findMany($rows->pluck('short_link_id'))->keyBy('id');
        $rules = RoutingRule::query()->findMany($rows->pluck('routing_rule_id')->filter())->keyBy('id');
        $variants = RoutingVariant::query()->findMany($rows->pluck('routing_variant_id')->filter())->keyBy('id');

        return $rows->map(fn ($row) => [
            'short_link_id' => (int) $row->short_link_id,
            'slug' => $links->get($row->short_link_id)?->slug,
            'routing_rule_id' => $row->routing_rule_id ? (int) $row->routing_rule_id : null,
            'routing_variant_id' => $row->routing_variant_id ? (int) $row->routing_variant_id : null,
            'rule_name' => $rules->get($row->routing_rule_id)?->name ?? 'Default destination',
            'variant_name' => $variants->get($row->routing_variant_id)?->name,
            ...$this->measures($row),
            'insufficient_evidence' => $this->insufficient($row),
        ])->all();
    }

    private function attributions(Workspace $workspace, AnalyticsFilters $filters): Builder
    {
        return ConversionAttribution::query()
            ->where('workspace_id', $workspace->id)
            ->whereBetween('issued_at', [$filters->from, $filters->to])
            ->when($filters->shortLinkId, fn (Builder $query, int $id) => $query->where('short_link_id', $id))
            ->when($filters->qrCodeId, fn (Builder $query, int $id) => $query->where('qr_code_id', $id))
            ->when($filters->routingRuleId, fn (Builder $query, int $id) => $query->where('routing_rule_id', $id))
            ->when($filters->routingVariantId, fn (Builder $query, int $id) => $query->where('routing_variant_id', $id))
            ->when($filters->metric, fn (Builder $query, string $metric) => $query->where('metric', $metric))
            ->when($filters->domainId, fn (Builder $query, int $id) => $query->whereIn(
                'short_link_id',
                ShortLink::query()->where('workspace_id', $workspace->id)->where('domain_id', $id)->select('id'),
            ))
            ->when($filters->folderId, fn (Builder $query, int $id) => $query->whereIn(
                'short_link_id',
                ShortLink::query()->where('workspace_id', $workspace->id)->where('folder_id', $id)->select('id'),
            ))
            ->when($filters->tagId, fn (Builder $query, int $id) => $query->whereIn(
                'short_link_id',
                DB::table('short_link_tag')->where('tag_id', $id)->select('short_link_id'),
            ));
    }

    private function counted(Builder $query): Builder
    {
        return $query
            ->selectRaw('count(*) as attributed_visits')
            ->selectRaw('count(converted_at) as conversions')
            ->selectRaw('sum(case when converted_at is null and expires_at > ? then 1 else 0 end) as pending', [now()]);
    }

    private function measures(?object $row): array
    {
        $attributed = (int) ($row->attributed_visits ?? 0);
        $conversions = (int) ($row->conversions ?? 0);

        return [
            'attributed_visits' => $attributed,
            'conversions' => $conversions,
            'pending' => (int) ($row->pending ?? 0),
            'rate' => $attributed > 0 ? round($conversions / $attributed * 100, 1) : null,
        ];
    }

    private function insufficient(object $row): bool
    {
        return (int) $row->attributed_visits < self::MINIMUM_ATTRIBUTED_VISITS;
    }
}
