<?php

namespace Tests\Feature\Conversions;

use App\Models\ConversionAttribution;
use App\Models\ShortLink;
use App\Services\Conversions\AttributionTokens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class ConversionRetentionTest extends TestCase
{
    use CreatesWorkspaces;
    use RefreshDatabase;

    public function test_pruning_removes_expired_unconverted_tokens_and_conversions_past_retention(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'goal', ['conversion_goal' => 'Registration']);
        $pending = $this->attribution($link, now()->subHours(2));
        $expired = $this->attribution($link, now()->subHours(25));
        $converted = $this->attribution($link, now()->subDays(30), converted: true);
        $retired = $this->attribution($link, now()->subDays(400), converted: true);

        $this->artisan('openlink:prune-analytics')->assertSuccessful();

        $this->assertEqualsCanonicalizing(
            [$pending->id, $converted->id],
            ConversionAttribution::query()->pluck('id')->all(),
        );
        $this->assertNull(ConversionAttribution::query()->find($expired->id));
        $this->assertNull(ConversionAttribution::query()->find($retired->id));
    }

    public function test_deleting_a_workspace_deletes_its_attributions(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'goal', ['conversion_goal' => 'Registration']);
        $this->attribution($link, now(), converted: true);

        $workspace->delete();

        $this->assertSame(0, ConversionAttribution::query()->count());
    }

    private function attribution(ShortLink $link, $issuedAt, bool $converted = false): ConversionAttribution
    {
        return ConversionAttribution::query()->create([
            'workspace_id' => $link->workspace_id,
            'short_link_id' => $link->id,
            'metric' => 'visit',
            'token_hash' => AttributionTokens::hash(Str::random(48)),
            'issued_at' => $issuedAt,
            'expires_at' => $issuedAt->copy()->addHours(AttributionTokens::WINDOW_HOURS),
            'converted_at' => $converted ? $issuedAt->copy()->addHour() : null,
        ]);
    }
}
