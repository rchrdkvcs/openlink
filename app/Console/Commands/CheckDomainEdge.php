<?php

namespace App\Console\Commands;

use App\Enums\DomainEdgeMode;
use App\Services\Dns\DomainDnsTarget;
use App\Services\DomainEdge\CloudflareZone;
use Closure;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use RuntimeException;
use Throwable;

#[Signature('openlink:edge-check')]
#[Description('Check that the domain edge is configured and reachable')]
class CheckDomainEdge extends Command
{
    private bool $passed = true;

    public function handle(): int
    {
        $mode = $this->check('Domain edge', fn (): DomainEdgeMode => DomainEdgeMode::fromConfig(config('openlink.domain_edge.driver')), fn (DomainEdgeMode $mode): string => $mode->value);

        if ($mode === DomainEdgeMode::Cloudflare) {
            $this->checkCloudflare(app(CloudflareZone::class));
        }

        if ($mode !== null) {
            $this->check('DNS Target resolves', function (): string {
                $target = app(DomainDnsTarget::class);
                $value = $target->value();

                if ($value === '') {
                    throw new RuntimeException('No DNS Target is configured. Set it in the instance settings.');
                }

                if ($target->targetIps() === []) {
                    throw new RuntimeException("{$value} does not resolve.");
                }

                return $value;
            });
        }

        $this->newLine();

        if (! $this->passed) {
            $this->components->error('The domain edge is not ready.');

            return self::FAILURE;
        }

        $this->components->info('The domain edge is ready.');

        return self::SUCCESS;
    }

    private function checkCloudflare(CloudflareZone $zone): void
    {
        $zone->forget();

        $this->check('Edge secret set', function (): string {
            $count = count((array) config('openlink.domain_edge.secrets', []));

            if ($count === 0) {
                throw new RuntimeException('Set OPENLINK_EDGE_SECRET to the Worker EDGE_SECRET.');
            }

            return $count === 1 ? '1 secret' : "{$count} secrets";
        });

        $this->check('Cloudflare API token valid', function () use ($zone): string {
            $status = $zone->tokenStatus();

            if ($status !== 'active') {
                throw new RuntimeException("The token status is \"{$status}\".");
            }

            return $status;
        });

        $this->check('Cloudflare zone', fn (): string => $zone->id());

        $this->check('Fallback origin active', function () use ($zone): string {
            $origin = $zone->fallbackOrigin();

            if ($origin['status'] !== 'active') {
                throw new RuntimeException("{$origin['origin']} is \"{$origin['status']}\". Check its DNS record is proxied.");
            }

            return $origin['origin'];
        });
    }

    private function check(string $label, Closure $probe, ?Closure $describe = null): mixed
    {
        try {
            $result = $probe();
            $this->components->twoColumnDetail($label, '<fg=green;options=bold>PASS</> '.($describe ? $describe($result) : $result));

            return $result;
        } catch (Throwable $exception) {
            $this->passed = false;
            $this->components->twoColumnDetail($label, '<fg=red;options=bold>FAIL</>');
            $this->components->bulletList([$this->explain($exception)]);

            return null;
        }
    }

    private function explain(Throwable $exception): string
    {
        if (! $exception instanceof RequestException) {
            return $exception->getMessage();
        }

        $status = $exception->response->status();
        $message = (string) ($exception->response->json('errors.0.message') ?? $exception->response->reason());

        return match (true) {
            $status === 401, $status === 403 => "Cloudflare refused the API token ({$status}: {$message}). It needs \"SSL and Certificates: Edit\" and \"Zone: Read\" on the zone.",
            default => "Cloudflare answered {$status}: {$message}",
        };
    }
}
