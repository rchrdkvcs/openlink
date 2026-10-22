<?php

namespace App\Models;

use App\Enums\DestinationHealthStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;

class DestinationCheck extends Model
{
    public const STALE_AFTER_DAYS = 7;

    public const QUEUED_RETRY_AFTER_SECONDS = 60;

    public const RUNNING_LEASE_SECONDS = 120;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => DestinationHealthStatus::class,
            'checked_at' => 'datetime',
            'requested_at' => 'datetime',
            'started_at' => 'datetime',
            'failing_since' => 'datetime',
            'incident_opened_at' => 'datetime',
            'recovered_at' => 'datetime',
        ];
    }

    public static function hashFor(string $url): string
    {
        return hash('sha256', $url);
    }

    public function host(): string
    {
        return rtrim(strtolower((string) parse_url($this->url, PHP_URL_HOST)), '.');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function scopeReferenced(Builder $query): Builder
    {
        return $query->where(fn (Builder $query) => $query
            ->whereExists(fn (QueryBuilder $links) => $links
                ->from('short_links')
                ->whereColumn('short_links.workspace_id', 'destination_checks.workspace_id')
                ->whereNull('short_links.archived_at')
                ->where(fn (QueryBuilder $urls) => $urls
                    ->whereColumn('short_links.destination_url', 'destination_checks.url')
                    ->orWhereColumn('short_links.fallback_url', 'destination_checks.url')))
            ->orWhereExists(fn (QueryBuilder $rules) => $rules
                ->from('routing_rules')
                ->join('short_links', 'short_links.id', '=', 'routing_rules.short_link_id')
                ->whereColumn('short_links.workspace_id', 'destination_checks.workspace_id')
                ->whereNull('short_links.archived_at')
                ->where('routing_rules.is_enabled', true)
                ->where('routing_rules.type', RoutingRule::TYPE_CONDITIONAL)
                ->whereColumn('routing_rules.destination_url', 'destination_checks.url'))
            ->orWhereExists(fn (QueryBuilder $variants) => $variants
                ->from('routing_variants')
                ->join('routing_rules', 'routing_rules.id', '=', 'routing_variants.routing_rule_id')
                ->join('short_links', 'short_links.id', '=', 'routing_rules.short_link_id')
                ->whereColumn('short_links.workspace_id', 'destination_checks.workspace_id')
                ->whereNull('short_links.archived_at')
                ->where('routing_rules.is_enabled', true)
                ->where('routing_rules.type', RoutingRule::TYPE_SPLIT_TEST)
                ->where('routing_variants.is_enabled', true)
                ->whereColumn('routing_variants.destination_url', 'destination_checks.url')));
    }

    public function hasIncident(): bool
    {
        return $this->incident_opened_at !== null;
    }

    public function executionState(): string
    {
        if ($this->execution_state === 'running' && ($this->started_at === null || $this->started_at->lessThanOrEqualTo(now()->subSeconds(self::RUNNING_LEASE_SECONDS)))) {
            return 'failed';
        }

        if ($this->execution_state !== null) {
            return $this->execution_state;
        }

        if ($this->requested_at !== null && ($this->checked_at === null || $this->requested_at->greaterThan($this->checked_at))) {
            return 'queued';
        }

        return $this->checked_at === null ? 'idle' : 'completed';
    }

    public function isPending(): bool
    {
        return in_array($this->executionState(), ['queued', 'running'], true);
    }

    public function canRetry(): bool
    {
        if (! $this->isPending()) {
            return true;
        }

        return $this->executionState() === 'queued'
            && ($this->requested_at === null || $this->requested_at->lessThanOrEqualTo(now()->subSeconds(self::QUEUED_RETRY_AFTER_SECONDS)));
    }

    public function isStale(): bool
    {
        return $this->checked_at !== null && $this->checked_at->lessThan(now()->subDays(self::STALE_AFTER_DAYS));
    }
}
