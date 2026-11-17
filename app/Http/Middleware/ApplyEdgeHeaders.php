<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyEdgeHeaders
{
    public const SECRET = 'X-Openlink-Edge-Secret';

    public const HOST = 'X-Openlink-Host';

    public const CLIENT_IP = 'X-Openlink-Client-Ip';

    public const COUNTRY = 'X-Openlink-Country';

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->signed($request)) {
            $this->apply($request);
        }

        $this->removeHeaders($request, fn (string $name): bool => str_starts_with($name, 'x-openlink-'));

        return $next($request);
    }

    private function signed(Request $request): bool
    {
        $provided = $request->headers->get(self::SECRET);

        if (! is_string($provided) || $provided === '') {
            return false;
        }

        foreach ((array) config('openlink.domain_edge.secrets', []) as $secret) {
            if (is_string($secret) && $secret !== '' && hash_equals($secret, $provided)) {
                return true;
            }
        }

        return false;
    }

    private function apply(Request $request): void
    {
        $host = $this->host((string) $request->headers->get(self::HOST));
        $ip = trim((string) $request->headers->get(self::CLIENT_IP));
        $country = strtoupper(trim((string) $request->headers->get(self::COUNTRY)));

        $this->removeHeaders($request, fn (string $name): bool => $name === 'forwarded' || str_starts_with($name, 'x-forwarded-') || $name === 'cf-ipcountry');
        $request->headers->set('X-Forwarded-Proto', 'https');
        $request->headers->set('X-Forwarded-Port', '443');

        if ($host !== null) {
            $request->headers->set('Host', $host);
            $request->headers->set('X-Forwarded-Host', $host);
            $request->server->set('HTTP_HOST', $host);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
            $request->headers->set('X-Forwarded-For', $ip);
            $request->server->set('REMOTE_ADDR', $ip);
        }

        if (preg_match('/^[A-Z]{2}$/', $country) === 1) {
            $request->headers->set('CF-IPCountry', $country);
        }
    }

    private function host(string $value): ?string
    {
        $host = strtolower(trim($value));

        return $host !== '' && filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false ? $host : null;
    }

    private function removeHeaders(Request $request, Closure $matches): void
    {
        foreach (array_keys($request->headers->all()) as $name) {
            if ($matches(strtolower($name))) {
                $request->headers->remove($name);
                $request->server->remove('HTTP_'.strtoupper(str_replace('-', '_', $name)));
            }
        }
    }
}
