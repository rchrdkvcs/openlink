<?php

namespace App\Services\Conversions;

use App\Enums\AnalyticsMetric;
use App\Models\ConversionAttribution;
use App\Models\QrCode;
use App\Models\ShortLink;
use App\Services\ResolutionContext;
use App\Services\RoutingDecision;
use Illuminate\Support\Str;
use Throwable;

class AttributionTokens
{
    public const QUERY_PARAMETER = 'openlink_attribution';

    public const WINDOW_HOURS = 24;

    private const TOKEN_LENGTH = 48;

    public function destinationFor(ShortLink $shortLink, ?QrCode $qrCode, ResolutionContext $context, RoutingDecision $decision): string
    {
        if (! $shortLink->tracksConversions() || ! $shortLink->workspace_id || $context->value('is_bot')) {
            return $decision->destinationUrl;
        }

        try {
            $token = $this->issue($shortLink, $qrCode, $context, $decision);
        } catch (Throwable $exception) {
            report($exception);

            return $decision->destinationUrl;
        }

        return $this->withToken($decision->destinationUrl, $token);
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    private function issue(ShortLink $shortLink, ?QrCode $qrCode, ResolutionContext $context, RoutingDecision $decision): string
    {
        $token = Str::random(self::TOKEN_LENGTH);

        ConversionAttribution::query()->create([
            'workspace_id' => $shortLink->workspace_id,
            'short_link_id' => $shortLink->id,
            'qr_code_id' => $qrCode?->id,
            'routing_rule_id' => $decision->rule?->id,
            'routing_variant_id' => $decision->variant?->id,
            'metric' => AnalyticsMetric::forEntry($qrCode)->value,
            'token_hash' => self::hash($token),
            'issued_at' => $context->occurredAt,
            'expires_at' => $context->occurredAt->addHours(self::WINDOW_HOURS),
        ]);

        return $token;
    }

    private function withToken(string $url, string $token): string
    {
        [$base, $fragment] = array_pad(explode('#', $url, 2), 2, null);
        $separator = match (true) {
            ! str_contains($base, '?') => '?',
            str_ends_with($base, '?'), str_ends_with($base, '&') => '',
            default => '&',
        };

        return $base.$separator.self::QUERY_PARAMETER.'='.$token.($fragment === null ? '' : '#'.$fragment);
    }
}
