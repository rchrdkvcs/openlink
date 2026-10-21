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
            ...$this->utm($request->query()),
        ], CarbonImmutable::now(), $this->visitorHash($request));
    }

    public function simulated(array $input, CarbonImmutable $occurredAt, string $seed): ResolutionContext
    {
        $referrer = $this->referrers->classify($this->text($input['referrer'] ?? null));
        $deviceType = $this->lower($input['device_type'] ?? null);

        return new ResolutionContext([
            'is_bot' => $deviceType === 'bot',
            'referrer_host' => Str::limit($referrer['host'] ?? '', 250, '') ?: null,
            'referrer_channel' => $referrer['channel'],
            'country' => ($country = $this->text($input['country'] ?? null)) ? strtoupper($country) : null,
            'language' => $this->lower($input['language'] ?? null),
            'device_type' => $deviceType,
            'browser' => $this->text($input['browser'] ?? null),
            'os' => $this->text($input['operating_system'] ?? null),
            ...$this->utm($input),
        ], $occurredAt, $this->seededVisitorHash($seed));
    }

    private function seededVisitorHash(string $seed): string
    {
        return substr(hash('sha256', 'routing-preview|'.$seed), 0, 32);
    }

    private function text(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : Str::limit($value, 250, '');
    }

    private function lower(mixed $value): ?string
    {
        $value = $this->text($value);

        return $value === null ? null : strtolower($value);
    }

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

    private function utm(array $values): array
    {
        $params = [];

        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $key) {
            $params[$key] = $this->text($values[$key] ?? null);
        }

        return $params;
    }
}
