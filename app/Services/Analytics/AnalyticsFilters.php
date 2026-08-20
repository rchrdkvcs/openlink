<?php

namespace App\Services\Analytics;

use App\Actions\Analytics\RecordAnalytics;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * The slice of analytics a report covers: a date range (preset or custom)
 * plus optional dimension filters. Every number in a report is computed
 * against the same instance, so all figures always agree.
 */
class AnalyticsFilters
{
    public const RANGES = ['24h', '7d', '14d', '30d', '90d', '12m', 'custom'];

    public function __construct(
        public readonly string $range,
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly ?int $shortLinkId = null,
        public readonly ?int $qrCodeId = null,
        public readonly ?int $bioPageId = null,
        public readonly ?int $domainId = null,
        public readonly ?int $folderId = null,
        public readonly ?int $tagId = null,
        public readonly ?int $routingRuleId = null,
        public readonly ?int $routingVariantId = null,
        public readonly ?string $metric = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $range = in_array($request->query('range'), self::RANGES, true) ? $request->query('range') : '30d';
        $now = CarbonImmutable::now();

        [$from, $to] = match ($range) {
            '24h' => [$now->subHours(24), $now],
            '7d' => [$now->subDays(7)->startOfDay(), $now],
            '14d' => [$now->subDays(14)->startOfDay(), $now],
            '90d' => [$now->subDays(90)->startOfDay(), $now],
            '12m' => [$now->subMonths(12)->startOfDay(), $now],
            'custom' => self::customRange($request, $now),
            default => [$now->subDays(30)->startOfDay(), $now],
        };

        $metric = $request->query('metric');

        return new self(
            range: $range,
            from: $from,
            to: $to,
            shortLinkId: self::id($request, 'link'),
            qrCodeId: self::id($request, 'qr'),
            bioPageId: self::id($request, 'bio'),
            domainId: self::id($request, 'domain'),
            folderId: self::id($request, 'folder'),
            tagId: self::id($request, 'tag'),
            routingRuleId: self::id($request, 'rule'),
            routingVariantId: self::id($request, 'variant'),
            metric: in_array($metric, [
                RecordAnalytics::METRIC_VISIT,
                RecordAnalytics::METRIC_SCAN,
                RecordAnalytics::METRIC_BIO_VIEW,
                RecordAnalytics::METRIC_BIO_ACTIVATION,
            ], true) ? $metric : null,
        );
    }

    public function forBioPage(int $bioPageId): self
    {
        return new self(
            range: $this->range,
            from: $this->from,
            to: $this->to,
            bioPageId: $bioPageId,
            metric: $this->metric,
        );
    }

    /** The immediately preceding window of the same length, for comparisons. */
    public function previous(): self
    {
        $duration = $this->from->diffInSeconds($this->to);

        return new self(
            range: $this->range,
            from: $this->from->subSeconds($duration),
            to: $this->from,
            shortLinkId: $this->shortLinkId,
            qrCodeId: $this->qrCodeId,
            bioPageId: $this->bioPageId,
            domainId: $this->domainId,
            folderId: $this->folderId,
            tagId: $this->tagId,
            routingRuleId: $this->routingRuleId,
            routingVariantId: $this->routingVariantId,
            metric: $this->metric,
        );
    }

    /** Bucket size for time series: fine enough to read, coarse enough to plot. */
    public function bucketUnit(): string
    {
        $days = $this->from->diffInDays($this->to);

        return match (true) {
            $days <= 2 => 'hour',
            $days <= 132 => 'day',
            default => 'month',
        };
    }

    /** @return array<string, string|int> Non-empty query parameters, for links and exports. */
    public function toQuery(): array
    {
        return array_filter([
            'range' => $this->range,
            'from' => $this->range === 'custom' ? $this->from->toDateString() : null,
            'to' => $this->range === 'custom' ? $this->to->toDateString() : null,
            'link' => $this->shortLinkId,
            'qr' => $this->qrCodeId,
            'bio' => $this->bioPageId,
            'domain' => $this->domainId,
            'folder' => $this->folderId,
            'tag' => $this->tagId,
            'rule' => $this->routingRuleId,
            'variant' => $this->routingVariantId,
            'metric' => $this->metric,
        ]);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private static function customRange(Request $request, CarbonImmutable $now): array
    {
        $from = rescue(fn () => CarbonImmutable::parse($request->query('from'))->startOfDay(), $now->subDays(30)->startOfDay(), false);
        $to = rescue(fn () => CarbonImmutable::parse($request->query('to'))->endOfDay(), $now, false);

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        return [$from, $to->min($now->endOfDay())];
    }

    private static function id(Request $request, string $key): ?int
    {
        $value = $request->query($key);

        return is_numeric($value) ? (int) $value : null;
    }
}
