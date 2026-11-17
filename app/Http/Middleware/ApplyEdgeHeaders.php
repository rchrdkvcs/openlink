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

        foreach ([self::SECRET, self::HOST, self::CLIENT_IP, self::COUNTRY] as $header) {
            $request->headers->remove($header);
        }

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
        $host = strtolower(trim((string) $request->headers->get(self::HOST)));

        if (preg_match('/^[a-z0-9.-]+$/', $host) === 1) {
            $request->headers->set('Host', $host);
            $request->headers->set('X-Forwarded-Host', $host);
            $request->headers->set('X-Forwarded-Proto', 'https');
            $request->headers->set('X-Forwarded-Port', '443');
            $request->server->set('HTTP_HOST', $host);
        }

        $ip = trim((string) $request->headers->get(self::CLIENT_IP));

        if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
            $request->headers->set('X-Forwarded-For', $ip);
            $request->server->set('REMOTE_ADDR', $ip);
        }

        $country = strtoupper(trim((string) $request->headers->get(self::COUNTRY)));
        $request->headers->remove('CF-IPCountry');

        if (preg_match('/^[A-Z]{2}$/', $country) === 1) {
            $request->headers->set('CF-IPCountry', $country);
        }
    }
}
