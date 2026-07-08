<?php

namespace App\Services\Analytics\Report;

use App\Models\QrCode;
use App\Models\RoutingRule;
use App\Models\RoutingVariant;
use App\Models\ShortLink;

class EntityRankingSection
{
    /** @return list<array<string, mixed>> */
    public function topLinks(AnalyticsEventSlice $slice, int $limit = 10): array
    {
        $rows = $slice->query()
            ->successful()
            ->whereNotNull('short_link_id')
            ->selectRaw('short_link_id, count(*) as total')
            ->selectRaw("sum(case when metric = 'visit' then 1 else 0 end) as visits")
            ->selectRaw("sum(case when metric = 'scan' then 1 else 0 end) as scans")
            ->selectRaw('count(distinct visitor_hash) as visitors')
            ->groupBy('short_link_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        $links = ShortLink::query()->with('domain')->findMany($rows->pluck('short_link_id'))->keyBy('id');

        return $rows->map(function ($row) use ($links) {
            $link = $links->get($row->short_link_id);

            return [
                'id' => $row->short_link_id,
                'slug' => $link?->slug ?? '(deleted link)',
                'short_url' => $link && $link->domain ? 'https://'.$link->domain->hostname.'/'.$link->slug : null,
                'destination_url' => $link?->destination_url,
                'visits' => (int) $row->visits,
                'scans' => (int) $row->scans,
                'visitors' => (int) $row->visitors,
                'total' => (int) $row->total,
            ];
        })->all();
    }

    /** @return list<array<string, mixed>> */
    public function topQrCodes(AnalyticsEventSlice $slice, int $limit = 10): array
    {
        $rows = $slice->query()
            ->successful()
            ->whereNotNull('qr_code_id')
            ->selectRaw('qr_code_id, count(*) as scans, count(distinct visitor_hash) as visitors')
            ->groupBy('qr_code_id')
            ->orderByDesc('scans')
            ->limit($limit)
            ->get();

        $qrCodes = QrCode::query()->with('shortLink')->findMany($rows->pluck('qr_code_id'))->keyBy('id');

        return $rows->map(function ($row) use ($qrCodes) {
            $qrCode = $qrCodes->get($row->qr_code_id);

            return [
                'id' => $row->qr_code_id,
                'name' => $qrCode?->name ?? '(deleted QR code)',
                'link_slug' => $qrCode?->shortLink?->slug,
                'scans' => (int) $row->scans,
                'visitors' => (int) $row->visitors,
            ];
        })->all();
    }

    /** @return list<array<string, mixed>> */
    public function routingPerformance(AnalyticsEventSlice $slice): array
    {
        $rows = $slice->query()
            ->successful()
            ->selectRaw('routing_rule_id, routing_variant_id, count(*) as total')
            ->selectRaw("sum(case when metric = 'visit' then 1 else 0 end) as visits")
            ->selectRaw("sum(case when metric = 'scan' then 1 else 0 end) as scans")
            ->selectRaw('count(distinct visitor_hash) as visitors')
            ->groupBy('routing_rule_id', 'routing_variant_id')
            ->orderByDesc('total')
            ->limit(20)
            ->get();

        $rules = RoutingRule::query()->findMany($rows->pluck('routing_rule_id')->filter())->keyBy('id');
        $variants = RoutingVariant::query()->findMany($rows->pluck('routing_variant_id')->filter())->keyBy('id');

        return $rows->map(function ($row) use ($rules, $variants) {
            $rule = $rules->get($row->routing_rule_id);
            $variant = $variants->get($row->routing_variant_id);

            return [
                'routing_rule_id' => $row->routing_rule_id ? (int) $row->routing_rule_id : null,
                'routing_variant_id' => $row->routing_variant_id ? (int) $row->routing_variant_id : null,
                'rule_name' => $rule?->name ?? 'Default destination',
                'variant_name' => $variant?->name,
                'visits' => (int) $row->visits,
                'scans' => (int) $row->scans,
                'visitors' => (int) $row->visitors,
                'total' => (int) $row->total,
            ];
        })->all();
    }
}
