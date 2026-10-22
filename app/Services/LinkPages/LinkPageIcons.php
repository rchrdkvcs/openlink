<?php

namespace App\Services\LinkPages;

use App\Models\ShortLink;

class LinkPageIcons
{
    private const HOST_ICONS = [
        'github.com' => 'github',
        'www.github.com' => 'github',
        'discord.com' => 'discord',
        'www.discord.com' => 'discord',
        'discord.gg' => 'discord',
        'youtube.com' => 'youtube',
        'www.youtube.com' => 'youtube',
        'youtu.be' => 'youtube',
        'open.spotify.com' => 'spotify',
        'spotify.com' => 'spotify',
        'www.spotify.com' => 'spotify',
        'reddit.com' => 'reddit',
        'www.reddit.com' => 'reddit',
        'linkedin.com' => 'linkedin',
        'www.linkedin.com' => 'linkedin',
    ];

    public function forLink(ShortLink $link): ?string
    {
        if (filled($link->password_hash)) {
            return null;
        }

        $host = strtolower((string) parse_url($link->destination_url, PHP_URL_HOST));

        return self::HOST_ICONS[$host] ?? null;
    }
}
