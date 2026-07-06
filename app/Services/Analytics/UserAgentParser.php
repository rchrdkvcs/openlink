<?php

namespace App\Services\Analytics;

/**
 * Dependency-free user agent parser tuned for analytics dimensions: family
 * level browser/OS names, a coarse device type, and bot detection. It favours
 * being right about the common 99% over exhaustive UA coverage.
 */
class UserAgentParser
{
    private const BOT_MARKERS = [
        'bot', 'crawl', 'spider', 'slurp', 'preview', 'prerender', 'headless',
        'lighthouse', 'phantomjs', 'curl/', 'wget/', 'python', 'go-http-client',
        'java/', 'libwww', 'okhttp', 'guzzlehttp', 'httpclient', 'axios/',
        'node-fetch', 'facebookexternalhit', 'whatsapp/', 'telegram', 'skypeuripreview',
        'embedly', 'quora link preview', 'vkshare', 'validator', 'monitor',
        'uptime', 'pingdom', 'gptbot', 'chatgpt-user', 'claudebot', 'anthropic',
        'perplexity', 'semrush', 'ahrefs', 'mj12', 'dotbot', 'petalbot', 'bytespider',
        'baiduspider', 'sogou', 'scrapy', 'feedfetcher', 'datanyze', 'zgrab', 'masscan',
    ];

    /** @return array{browser: string, os: string, device_type: string, is_bot: bool} */
    public function parse(?string $userAgent): array
    {
        $agent = mb_strtolower(trim((string) $userAgent));

        if ($agent === '') {
            return ['browser' => 'Unknown', 'os' => 'Unknown', 'device_type' => 'unknown', 'is_bot' => false];
        }

        $isBot = $this->isBot($agent);

        return [
            'browser' => $this->browser($agent),
            'os' => $this->os($agent),
            'device_type' => $isBot ? 'bot' : $this->deviceType($agent),
            'is_bot' => $isBot,
        ];
    }

    private function isBot(string $agent): bool
    {
        foreach (self::BOT_MARKERS as $marker) {
            if (str_contains($agent, $marker)) {
                return true;
            }
        }

        // Real browsers always announce Mozilla, Opera, or a known engine.
        return ! str_contains($agent, 'mozilla') && ! str_contains($agent, 'opera');
    }

    private function browser(string $agent): string
    {
        return match (true) {
            str_contains($agent, 'edg/') || str_contains($agent, 'edge/') || str_contains($agent, 'edgios') || str_contains($agent, 'edga') => 'Edge',
            str_contains($agent, 'opr/') || str_contains($agent, 'opera') => 'Opera',
            str_contains($agent, 'samsungbrowser') => 'Samsung Internet',
            str_contains($agent, 'ucbrowser') => 'UC Browser',
            str_contains($agent, 'firefox/') || str_contains($agent, 'fxios') => 'Firefox',
            str_contains($agent, 'vivaldi') => 'Vivaldi',
            str_contains($agent, 'brave') => 'Brave',
            str_contains($agent, 'duckduckgo') => 'DuckDuckGo',
            str_contains($agent, 'crios') || str_contains($agent, 'chrome/') || str_contains($agent, 'chromium') => 'Chrome',
            str_contains($agent, 'msie') || str_contains($agent, 'trident/') => 'Internet Explorer',
            str_contains($agent, 'safari/') && str_contains($agent, 'version/') => 'Safari',
            str_contains($agent, 'safari') => 'Safari',
            default => 'Other',
        };
    }

    private function os(string $agent): string
    {
        return match (true) {
            str_contains($agent, 'windows phone') => 'Windows Phone',
            str_contains($agent, 'windows') => 'Windows',
            str_contains($agent, 'android') => 'Android',
            str_contains($agent, 'iphone') || str_contains($agent, 'ipad') || str_contains($agent, 'ipod') || str_contains($agent, 'ios') => 'iOS',
            str_contains($agent, 'mac os') || str_contains($agent, 'macintosh') => 'macOS',
            str_contains($agent, 'cros') => 'ChromeOS',
            str_contains($agent, 'linux') || str_contains($agent, 'x11') => 'Linux',
            default => 'Other',
        };
    }

    private function deviceType(string $agent): string
    {
        return match (true) {
            str_contains($agent, 'ipad') || str_contains($agent, 'tablet') || (str_contains($agent, 'android') && ! str_contains($agent, 'mobile')) => 'tablet',
            str_contains($agent, 'mobi') || str_contains($agent, 'iphone') || str_contains($agent, 'ipod') || str_contains($agent, 'windows phone') => 'mobile',
            default => 'desktop',
        };
    }
}
