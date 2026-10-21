<?php

namespace Tests\Feature\LinkPreviews;

use App\Models\AnalyticsEvent;
use App\Models\Domain;
use App\Models\QrCode;
use App\Services\Analytics\Outcome;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\UserAgents;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class LinkPreviewCrawlerTest extends TestCase
{
    use CreatesWorkspaces;
    use RefreshDatabase;

    private const SLACKBOT = 'Slackbot-LinkExpanding 1.0 (+https://api.slack.com/robots)';

    public function test_supported_crawler_receives_the_custom_link_preview_without_consuming_a_visit(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain('go.example.test');
        $link = $this->shortLink($workspace, $domain, 'launch', [
            'visit_limit' => 1,
            'preview_title' => 'Spring launch',
            'preview_description' => 'Everything new this season.',
        ]);

        $this->withHeader('User-Agent', self::SLACKBOT)
            ->get('http://go.example.test/launch')
            ->assertOk()
            ->assertSee('<meta property="og:title" content="Spring launch">', false)
            ->assertSee('<meta property="og:description" content="Everything new this season.">', false)
            ->assertSee('<meta property="og:url" content="https://go.example.test/launch">', false)
            ->assertDontSee('example.com/landing');

        $this->assertSame(0, $link->fresh()->successful_visits);
        $this->assertSame(0, AnalyticsEvent::query()->count());
    }

    public static function crawlers(): array
    {
        return [
            'facebook' => ['facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)'],
            'x' => ['Twitterbot/1.0'],
            'linkedin' => ['LinkedInBot/1.0 (compatible; Mozilla/5.0; Apache-HttpClient +http://www.linkedin.com)'],
            'slack' => [self::SLACKBOT],
            'discord' => ['Mozilla/5.0 (compatible; Discordbot/2.0; +https://discordapp.com)'],
            'telegram' => ['TelegramBot (like TwitterBot)'],
            'whatsapp' => ['WhatsApp/2.23.20.0 A'],
            'imessage' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_1) AppleWebKit/601.2.4 (KHTML, like Gecko) Version/9.0.1 Safari/601.2.4 facebookexternalhit/1.1 Facebot Twitterbot/1.0'],
        ];
    }

    #[DataProvider('crawlers')]
    public function test_each_supported_crawler_receives_link_preview_metadata(string $userAgent): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain('go.example.test');
        $this->shortLink($workspace, $domain, 'launch', ['preview_title' => 'Spring launch']);

        $this->withHeader('User-Agent', $userAgent)
            ->get('http://go.example.test/launch')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-cache, private')
            ->assertSee('<meta property="og:title" content="Spring launch">', false);
    }

    public function test_ordinary_visitors_are_redirected_and_counted_when_a_link_preview_exists(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain('go.example.test');
        $link = $this->shortLink($workspace, $domain, 'launch', ['preview_title' => 'Spring launch']);

        $this->withHeader('User-Agent', UserAgents::SAFARI_MAC)
            ->get('http://go.example.test/launch')
            ->assertRedirect('https://example.com/landing');

        $this->assertSame(1, $link->fresh()->successful_visits);
        $this->assertDatabaseHas('analytics_events', ['short_link_id' => $link->id, 'outcome' => Outcome::SUCCESS, 'is_bot' => false]);
    }

    public function test_crawlers_follow_the_ordinary_redirect_for_public_links_without_a_link_preview(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain('go.example.test');
        $this->shortLink($workspace, $domain, 'plain');

        $this->withHeader('User-Agent', self::SLACKBOT)
            ->get('http://go.example.test/plain')
            ->assertRedirect('https://example.com/landing');
    }

    public function test_qr_code_entries_keep_their_resolution_for_crawlers(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain('go.example.test');
        $link = $this->shortLink($workspace, $domain, 'launch', ['preview_title' => 'Spring launch']);
        $qrCode = QrCode::create(['workspace_id' => $workspace->id, 'short_link_id' => $link->id, 'name' => 'Poster', 'token' => 'poster-token']);

        $this->withHeader('User-Agent', self::SLACKBOT)
            ->get('http://go.example.test/qr/'.$qrCode->token)
            ->assertRedirect('https://example.com/landing');

        $this->assertDatabaseHas('analytics_events', ['qr_code_id' => $qrCode->id, 'metric' => 'scan']);
    }

    public static function privateStates(): array
    {
        return [
            'protected' => [fn () => ['password_hash' => Hash::make('secret')]],
            'disabled' => [fn () => ['is_enabled' => false]],
            'archived' => [fn () => ['archived_at' => now()]],
            'scheduled' => [fn () => ['activates_at' => now()->addDay()]],
            'expired' => [fn () => ['expires_at' => now()->subMinute()]],
            'visit limit reached' => [fn () => ['visit_limit' => 1, 'successful_visits' => 1]],
        ];
    }

    #[DataProvider('privateStates')]
    public function test_protected_and_unavailable_links_return_neutral_metadata_to_crawlers(callable $state): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain('go.example.test');
        $link = $this->shortLink($workspace, $domain, 'secret', [
            'destination_url' => 'https://private.example.com/destination',
            'fallback_url' => 'https://private.example.com/fallback',
            'preview_title' => 'Private campaign title',
            'preview_description' => 'Private campaign description',
            'preview_image_path' => 'link-previews/'.str_repeat('a', 40).'.jpg',
            ...$state(),
        ]);

        $response = $this->withHeader('User-Agent', self::SLACKBOT)->get('http://go.example.test/secret');

        $this->assertNeutral($response);
        $this->assertSame((int) $link->successful_visits, $link->fresh()->successful_visits);
        $this->assertSame(0, AnalyticsEvent::query()->count());
    }

    public function test_unknown_slugs_and_unusable_domains_return_the_same_neutral_metadata(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain('go.example.test');
        [$other, $disabled] = $this->workspaceWithActiveDomain('off.example.test');
        $disabled->update(['status' => Domain::STATUS_DISABLED]);
        $this->shortLink($other, $disabled, 'launch', ['preview_title' => 'Private campaign title']);

        $missing = $this->withHeader('User-Agent', self::SLACKBOT)->get('http://go.example.test/nothing');
        $unusable = $this->withHeader('User-Agent', self::SLACKBOT)->get('http://off.example.test/launch');

        $this->assertNeutral($missing);
        $this->assertNeutral($unusable);
        $this->assertSame($missing->getContent(), $unusable->getContent());
    }

    public function test_spoofed_crawler_user_agent_cannot_unlock_a_protected_link(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain('go.example.test');
        $link = $this->shortLink($workspace, $domain, 'secret', [
            'destination_url' => 'https://private.example.com/destination',
            'password_hash' => Hash::make('secret'),
        ]);

        $this->assertNeutral($this->withHeader('User-Agent', 'Twitterbot/1.0')->get('http://go.example.test/secret'));

        $this->withHeader('User-Agent', 'Twitterbot/1.0')
            ->post('http://go.example.test/password/'.$link->id, ['password' => 'wrong'])
            ->assertForbidden();

        $this->assertSame(0, $link->fresh()->successful_visits);
    }

    public function test_link_preview_text_is_escaped_in_metadata(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain('go.example.test');
        $this->shortLink($workspace, $domain, 'launch', [
            'preview_title' => '"><script>alert(1)</script>',
            'preview_description' => "Tom & Jerry's \"show\"",
        ]);

        $this->withHeader('User-Agent', self::SLACKBOT)
            ->get('http://go.example.test/launch')
            ->assertOk()
            ->assertDontSee('<script>', false)
            ->assertSee('content="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"', false)
            ->assertSee('content="Tom &amp; Jerry&#039;s &quot;show&quot;"', false);
    }

    private function assertNeutral($response): void
    {
        $response->assertOk()
            ->assertSee('<meta property="og:title" content="'.config('app.name').'">', false)
            ->assertDontSee('private.example.com')
            ->assertDontSee('Private campaign')
            ->assertDontSee('og:image')
            ->assertDontSee('og:url');
    }
}
