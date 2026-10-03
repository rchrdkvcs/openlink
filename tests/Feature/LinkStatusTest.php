<?php

namespace Tests\Feature;

use App\Enums\LinkStatus;
use App\Models\Domain;
use App\Models\ShortLink;
use App\Models\User;
use App\Models\Workspace;
use App\Services\ShortLinks\ShortLinkLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LinkStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_php_status_and_sql_filter_agree_for_every_lifecycle_combination(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00'));
        $expected = [];

        foreach ($this->scenarios() as $slug => [$attributes, $status]) {
            $link = ShortLink::create([
                'workspace_id' => $this->domain()->workspace_id,
                'domain_id' => $this->domain()->id,
                'slug' => $slug,
                'destination_url' => 'https://example.com/'.$slug,
                ...$attributes,
            ]);
            $expected[$status->value][] = $link->id;
        }

        $lifecycle = app(ShortLinkLifecycle::class);

        foreach (LinkStatus::cases() as $status) {
            $fromPhp = ShortLink::query()->orderBy('id')->get()
                ->filter(fn (ShortLink $link) => $lifecycle->statusOf($link) === $status)
                ->pluck('id')->values()->all();
            $query = ShortLink::query();
            $lifecycle->whereStatus($query, $status);
            $fromSql = $query->orderBy('id')->pluck('id')->all();

            $this->assertSame($expected[$status->value] ?? [], $fromPhp, "PHP status {$status->value}");
            $this->assertSame($fromPhp, $fromSql, "SQL status {$status->value}");
        }
    }

    private function scenarios(): array
    {
        $now = now();

        return [
            'plain' => [[], LinkStatus::Active],
            'archived' => [['archived_at' => $now->copy()->subDay(), 'is_enabled' => false], LinkStatus::Archived],
            'disabled' => [['is_enabled' => false, 'activates_at' => $now->copy()->addDay()], LinkStatus::Disabled],
            'scheduled' => [['activates_at' => $now->copy()->addMinute(), 'expires_at' => $now->copy()->subDay()], LinkStatus::Scheduled],
            'activates-now' => [['activates_at' => $now->copy()], LinkStatus::Active],
            'expired' => [['expires_at' => $now->copy()->subSecond()], LinkStatus::Expired],
            'expires-now' => [['expires_at' => $now->copy()], LinkStatus::Active],
            'future-expiry' => [['expires_at' => $now->copy()->addDay()], LinkStatus::Active],
            'limit-reached' => [['visit_limit' => 3, 'successful_visits' => 3], LinkStatus::Expired],
            'limit-open' => [['visit_limit' => 3, 'successful_visits' => 2], LinkStatus::Active],
            'started' => [['activates_at' => $now->copy()->subDay(), 'expires_at' => $now->copy()->addDay()], LinkStatus::Active],
        ];
    }

    private function domain(): Domain
    {
        if ($domain = Domain::query()->first()) {
            return $domain;
        }

        $user = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $user->id, 'name' => 'Status', 'slug' => 'status', 'settings' => []]);

        return Domain::create([
            'workspace_id' => $workspace->id,
            'hostname' => 'status.test',
            'status' => Domain::STATUS_ACTIVE,
            'verification_token' => 'test-token-'.str()->random(12),
            'verified_at' => now(),
        ]);
    }
}
