<?php

namespace App\Services\Analytics;

use App\Enums\AnalyticsMetric;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class AnalyticsFilters
{
    public const RANGES = ['24h', '7d', '14d', '30d', '90d', '12m', 'custom'];

    public function __construct(
        public readonly string $range,
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly ?int $shortLinkId = null,
        public readonly ?int $qrCodeId = null,
        public readonly ?int $domainId = null,
        public readonly ?int $folderId = null,
        public readonly ?int $tagId = null,
        public readonly ?int $routingRuleId = null,
        public readonly ?int $routingVariantId = null,
        public readonly ?string $metric = null,
        public readonly array $unavailable = [],
    ) {}

    public static function fromRequest(Request $request): self
    {
        return self::fromQuery($request->query());
    }

    public static function fromQuery(array $query): self
    {
        $range = in_array($query['range'] ?? null, self::RANGES, true) ? $query['range'] : '30d';
        $now = CarbonImmutable::now();

        [$from, $to] = match ($range) {
            '24h' => [$now->subHours(24), $now],
            '7d' => [$now->subDays(7)->startOfDay(), $now],
            '14d' => [$now->subDays(14)->startOfDay(), $now],
            '90d' => [$now->subDays(90)->startOfDay(), $now],
            '12m' => [$now->subMonths(12)->startOfDay(), $now],
            'custom' => self::customRange($query, $now),
            default => [$now->subDays(30)->startOfDay(), $now],
        };

        $metric = $query['metric'] ?? null;

        return new self(
            range: $range,
            from: $from,
            to: $to,
            shortLinkId: self::id($query, 'link'),
            qrCodeId: self::id($query, 'qr'),
            domainId: self::id($query, 'domain'),
            folderId: self::id($query, 'folder'),
            tagId: self::id($query, 'tag'),
            routingRuleId: self::id($query, 'rule'),
            routingVariantId: self::id($query, 'variant'),
            metric: is_string($metric) ? AnalyticsMetric::tryFrom($metric)?->value : null,
        );
    }

    public function previous(): self
    {
        $duration = $this->from->diffInSeconds($this->to);

        return new self(
            range: $this->range,
            from: $this->from->subSeconds($duration),
            to: $this->from,
            shortLinkId: $this->shortLinkId,
            qrCodeId: $this->qrCodeId,
            domainId: $this->domainId,
            folderId: $this->folderId,
            tagId: $this->tagId,
            routingRuleId: $this->routingRuleId,
            routingVariantId: $this->routingVariantId,
            metric: $this->metric,
            unavailable: $this->unavailable,
        );
    }

    public function withUnavailable(array $keys): self
    {
        return new self(
            range: $this->range,
            from: $this->from,
            to: $this->to,
            shortLinkId: $this->shortLinkId,
            qrCodeId: $this->qrCodeId,
            domainId: $this->domainId,
            folderId: $this->folderId,
            tagId: $this->tagId,
            routingRuleId: $this->routingRuleId,
            routingVariantId: $this->routingVariantId,
            metric: $this->metric,
            unavailable: array_values($keys),
        );
    }

    public function targets(): array
    {
        return array_filter([
            'link' => $this->shortLinkId,
            'qr' => $this->qrCodeId,
            'domain' => $this->domainId,
            'folder' => $this->folderId,
            'tag' => $this->tagId,
            'rule' => $this->routingRuleId,
            'variant' => $this->routingVariantId,
        ]);
    }

    public function bucketUnit(): string
    {
        $days = $this->from->diffInDays($this->to);

        return match (true) {
            $days <= 2 => 'hour',
            $days <= 132 => 'day',
            default => 'month',
        };
    }

    public function toQuery(): array
    {
        return array_filter([
            'range' => $this->range,
            'from' => $this->range === 'custom' ? $this->from->toDateString() : null,
            'to' => $this->range === 'custom' ? $this->to->toDateString() : null,
            'link' => $this->shortLinkId,
            'qr' => $this->qrCodeId,
            'domain' => $this->domainId,
            'folder' => $this->folderId,
            'tag' => $this->tagId,
            'rule' => $this->routingRuleId,
            'variant' => $this->routingVariantId,
            'metric' => $this->metric,
        ]);
    }

    private static function customRange(array $query, CarbonImmutable $now): array
    {
        $from = rescue(fn () => CarbonImmutable::parse($query['from'] ?? null)->startOfDay(), $now->subDays(30)->startOfDay(), false);
        $to = rescue(fn () => CarbonImmutable::parse($query['to'] ?? null)->endOfDay(), $now, false);

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        return [$from, $to->min($now->endOfDay())];
    }

    private static function id(array $query, string $key): ?int
    {
        $value = $query[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
