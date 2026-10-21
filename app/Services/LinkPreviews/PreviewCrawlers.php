<?php

namespace App\Services\LinkPreviews;

use Illuminate\Http\Request;

class PreviewCrawlers
{
    private const MARKERS = [
        'facebookexternalhit', 'facebot', 'twitterbot', 'linkedinbot', 'slackbot-linkexpanding',
        'slack-imgproxy', 'discordbot', 'telegrambot', 'whatsapp/', 'pinterestbot', 'pinterest/0.',
        'redditbot', 'skypeuripreview', 'applebot', 'mastodon/', 'bluesky cardyb', 'iframely',
        'embedly', 'vkshare', 'w3c_validator',
    ];

    public function matches(Request $request): bool
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return false;
        }

        $agent = mb_strtolower((string) $request->userAgent());

        foreach (self::MARKERS as $marker) {
            if (str_contains($agent, $marker)) {
                return true;
            }
        }

        return false;
    }
}
