<?php

namespace App\Services;

use App\Services\Analytics\ReferrerClassifier;
use App\Services\Analytics\UserAgentParser;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ResolutionContextFactory
{
    public function __construct(
        private readonly UserAgentParser $userAgents,
        private readonly ReferrerClassifier $referrers,
    ) {}

    public function fromRequest(Request $request): ResolutionContext
    {
        $agent = $this->userAgents->parse($request->userAgent());
        $referrer = $this->referrers->classify($request->headers->get('referer'));

        return new ResolutionContext([
            'is_bot' => $agent['is_bot'],
            'referrer_host' => Str::limit($referrer['host'] ?? '', 250, '') ?: null,
            'referrer_channel' => $referrer['channel'],
            'country' => $this->country($request),
            'language' => $this->language($request),
            'device_type' => $agent['device_type'],
            'browser' => $agent['browser'],
            'os' => $agent['os'],
            ...$this->utm($request),
        ], CarbonImmutable::now(), $this->visitorHash($request));
    }

    /**
     * A privacy-preserving visitor identifier: hashed from IP + user agent
     * with a salt that rotates daily, so visitors can be counted as unique
     * within a day but never tracked across days or identified.
     */
    private function visitorHash(Request $request): string
    {
        $salt = config('app.key').now()->toDateString();

        return substr(hash('sha256', $salt.'|'.$request->ip().'|'.$request->userAgent()), 0, 32);
    }

    private function country(Request $request): ?string
    {
        $headers = [
            'CF-IPCountry',
            'CloudFront-Viewer-Country',
            'X-Vercel-IP-Country',
            'Fly-Client-IP-Country',
            'X-Appengine-Country',
            'X-Country-Code',
            'X-Geo-Country',
        ];

        foreach ($headers as $header) {
            $value = strtoupper((string) $request->headers->get($header));

            if (preg_match('/^[A-Z]{2}$/', $value) && ! in_array($value, ['XX', 'T1'], true)) {
                return $value;
            }
        }

        return null;
    }

    private function language(Request $request): ?string
    {
        $header = (string) $request->headers->get('Accept-Language');

        return preg_match('/^\s*([a-zA-Z]{2})\b/', $header, $matches)
            ? strtolower($matches[1])
            : null;
    }

    /** @return array<string, ?string> */
    private function utm(Request $request): array
    {
        $params = [];

        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $key) {
            $value = $request->query($key);
            $value = is_string($value) ? trim($value) : '';

            $params[$key] = $value === '' ? null : Str::limit($value, 250, '');
        }

        return $params;
    }
}
