<?php

namespace App\Services\Analytics;

/**
 * Normalises a Referer header into a clean host plus a marketing channel
 * (direct, search, social, video, email, messaging, ai, referral) so teams
 * can read acquisition at a glance without memorising hostnames.
 */
class ReferrerClassifier
{
    public const CHANNEL_DIRECT = 'direct';

    public const CHANNEL_SEARCH = 'search';

    public const CHANNEL_SOCIAL = 'social';

    public const CHANNEL_VIDEO = 'video';

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_MESSAGING = 'messaging';

    public const CHANNEL_AI = 'ai';

    public const CHANNEL_REFERRAL = 'referral';

    private const CHANNELS = [
        self::CHANNEL_SEARCH => [
            'google.', 'bing.com', 'duckduckgo.com', 'search.yahoo.', 'ecosia.org',
            'qwant.com', 'baidu.com', 'yandex.', 'startpage.com', 'search.brave.com',
            'presearch.', 'mojeek.com', 'kagi.com',
        ],
        self::CHANNEL_SOCIAL => [
            'facebook.', 'fb.me', 'instagram.', 'l.instagram.com', 'twitter.', 't.co',
            'x.com', 'linkedin.', 'lnkd.in', 'reddit.', 'pinterest.', 'tiktok.',
            'threads.net', 'bsky.app', 'mastodon.', 'snapchat.', 'vk.com', 'weibo.',
            'tumblr.', 'nextdoor.',
        ],
        self::CHANNEL_VIDEO => [
            'youtube.', 'youtu.be', 'vimeo.com', 'twitch.tv', 'dailymotion.com',
        ],
        self::CHANNEL_EMAIL => [
            'mail.google.com', 'gmail.com', 'outlook.', 'mail.yahoo.', 'mail.proton.me',
            'webmail.', 'mail.orange.fr', 'zimbra.', 'roundcube.', 'gmx.',
        ],
        self::CHANNEL_MESSAGING => [
            'whatsapp.', 'wa.me', 't.me', 'telegram.', 'messenger.com', 'discord.',
            'slack.com', 'teams.microsoft.com', 'signal.org', 'line.me', 'wechat.',
        ],
        self::CHANNEL_AI => [
            'chatgpt.com', 'chat.openai.com', 'claude.ai', 'perplexity.ai',
            'gemini.google.com', 'copilot.microsoft.com', 'mistral.ai', 'chat.deepseek.com',
        ],
    ];

    /** @return array{host: ?string, channel: string} */
    public function classify(?string $referrer): array
    {
        $host = $this->host($referrer);

        if ($host === null) {
            return ['host' => null, 'channel' => self::CHANNEL_DIRECT];
        }

        foreach (self::CHANNELS as $channel => $needles) {
            foreach ($needles as $needle) {
                if ($this->matches($host, $needle)) {
                    return ['host' => $host, 'channel' => $channel];
                }
            }
        }

        return ['host' => $host, 'channel' => self::CHANNEL_REFERRAL];
    }

    /**
     * A trailing-dot needle ("google.") matches that name under any TLD and
     * any subdomain; a full domain ("t.co") matches exactly or as a suffix
     * label — never as a bare substring, which would make "chatgpt.com"
     * match "t.co".
     */
    private function matches(string $host, string $needle): bool
    {
        if (str_ends_with($needle, '.')) {
            return str_starts_with($host, $needle) || str_contains($host, '.'.$needle);
        }

        return $host === $needle || str_ends_with($host, '.'.$needle);
    }

    private function host(?string $referrer): ?string
    {
        $referrer = trim((string) $referrer);

        if ($referrer === '') {
            return null;
        }

        // Android app referrers arrive as android-app://<package>.
        if (str_starts_with($referrer, 'android-app://')) {
            return substr($referrer, strlen('android-app://'));
        }

        $host = parse_url($referrer, PHP_URL_HOST) ?: null;

        if ($host === null) {
            return null;
        }

        $host = mb_strtolower($host);

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }
}
