<?php

namespace App\Services\Analytics;

use App\Enums\AnalyticsMetric;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class AnalyticsFilters
{
    public const RANGES = ['24h', '7d', '14d', '30d', '90d', '12m', 'custom'];

    public const TARGET_KEYS = ['link', 'qr', 'domain', 'folder', 'tag', 'rule', 'variant'];

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

        $end = $now->endOfSecond();

        [$from, $to] = match ($range) {
            '24h' => [$now->subHours(24)->startOfSecond(), $end],
            '7d' => [$now->subDays(7)->startOfDay(), $end],
            '14d' => [$now->subDays(14)->startOfDay(), $end],
            '90d' => [$now->subDays(90)->startOfDay(), $end],
            '12m' => [$now->subMonths(12)->startOfDay(), $end],
            'custom' => self::customRange($query, $now),
            default => [$now->subDays(30)->startOfDay(), $end],
        };

        $metric = $query['metric'] ?? null;
        $metricValue = is_string($metric) ? AnalyticsMetric::tryFrom($metric)?->value : null;
        $ids = [];
        $invalid = [];

        foreach (self::TARGET_KEYS as $key) {
            $ids[$key] = self::id($query, $key);

            if ($ids[$key] === null && self::present($query, $key)) {
                $invalid[] = $key;
            }
        }

        if ($metricValue === null && self::present($query, 'metric')) {
            $invalid[] = 'metric';
        }

        return new self(
            range: $range,
            from: $from,
            to: $to,
            shortLinkId: $ids['link'],
            qrCodeId: $ids['qr'],
            domainId: $ids['domain'],
            folderId: $ids['folder'],
            tagId: $ids['tag'],
            routingRuleId: $ids['rule'],
            routingVariantId: $ids['variant'],
            metric: $metricValue,
            unavailable: $invalid,
        );
    }

    public function previous(): self
    {
        return $this->with(
            from: $this->from->sub($this->from->diff($this->to)),
            to: $this->from,
        );
    }

    public function withUnavailable(array $keys): self
    {
        return $this->with(unavailable: array_values(array_unique([...$this->unavailable, ...$keys])));
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
        ], fn (?int $id) => $id !== null);
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

    private function with(
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $to = null,
        ?array $unavailable = null,
    ): self {
        return new self(
            range: $this->range,
            from: $from ?? $this->from,
            to: $to ?? $this->to,
            shortLinkId: $this->shortLinkId,
            qrCodeId: $this->qrCodeId,
            domainId: $this->domainId,
            folderId: $this->folderId,
            tagId: $this->tagId,
            routingRuleId: $this->routingRuleId,
            routingVariantId: $this->routingVariantId,
            metric: $this->metric,
            unavailable: $unavailable ?? $this->unavailable,
        );
    }

    private static function present(array $query, string $key): bool
    {
        return array_key_exists($key, $query) && $query[$key] !== null && $query[$key] !== '';
    }

    private static function id(array $query, string $key): ?int
    {
        $value = $query[$key] ?? null;

        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        return is_string($value) && preg_match('/^[1-9][0-9]{0,17}$/', $value) === 1 ? (int) $value : null;
    }
}
