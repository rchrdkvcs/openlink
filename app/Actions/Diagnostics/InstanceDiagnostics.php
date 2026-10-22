<?php

namespace App\Actions\Diagnostics;

use App\Enums\CheckState;
use App\Mail\InstanceDiagnosticsTestMail;
use App\Models\DiagnosticObservation;
use App\Models\User;
use App\Services\Diagnostics\Heartbeats;
use App\Services\Diagnostics\Probes;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Throwable;

class InstanceDiagnostics
{
    public const MAIL_TIMEOUT_SECONDS = 10;

    public function __construct(
        private readonly Heartbeats $heartbeats,
        private readonly Probes $probes,
    ) {}

    public function report(): array
    {
        $observations = DiagnosticObservation::query()->get()->keyBy('name');

        $worker = $this->worker($observations->get(DiagnosticObservation::WORKER));

        return [
            'checks' => [
                'database' => $this->probed($observations, 'database', 'PostgreSQL', $this->databaseText()),
                'redis' => $this->redis($observations),
                'worker' => $worker,
                'scheduler' => $this->scheduler($observations->get(DiagnosticObservation::SCHEDULER)),
                'mail' => $this->mail(),
                'storage' => $this->probed($observations, 'storage', 'Storage', $this->storageText()),
                'analytics' => $this->analytics(CheckState::from($worker['state'])),
            ],
            'mailTest' => $this->mailTest($observations->get(DiagnosticObservation::MAIL_TEST)),
            'heartbeat' => [
                'cadenceMinutes' => $this->heartbeats->cadenceMinutes(),
                'staleAfterMinutes' => $this->heartbeats->staleAfterMinutes(),
            ],
        ];
    }

    public function recheck(): void
    {
        $this->observe('database', fn () => $this->probes->database());
        $this->probes->storageIsLocal()
            ? $this->observe('storage', fn () => $this->probes->storage())
            : $this->store('storage', CheckState::Disabled);

        if ($this->redisInUse()->isEmpty()) {
            $this->store('redis', CheckState::Disabled);

            return;
        }

        $this->observe('redis', fn () => $this->probes->redis());
    }

    public function canSendTestMail(): bool
    {
        return $this->mailConfiguration()['state'] === CheckState::Healthy;
    }

    public function sendTestMail(User $admin): bool
    {
        $mailer = (string) config('mail.default');
        $socketTimeout = ini_get('default_socket_timeout');
        ini_set('default_socket_timeout', (string) self::MAIL_TIMEOUT_SECONDS);

        if (blank(config("mail.mailers.{$mailer}.timeout")) || (int) config("mail.mailers.{$mailer}.timeout") > self::MAIL_TIMEOUT_SECONDS) {
            config(["mail.mailers.{$mailer}.timeout" => self::MAIL_TIMEOUT_SECONDS]);
            Mail::purge($mailer);
        }

        try {
            Mail::mailer($mailer)->to($admin->email)->send(new InstanceDiagnosticsTestMail);
            $this->store(DiagnosticObservation::MAIL_TEST, CheckState::Healthy);

            return true;
        } catch (Throwable $exception) {
            report($exception);
            $this->store(DiagnosticObservation::MAIL_TEST, CheckState::Unavailable);

            return false;
        } finally {
            ini_set('default_socket_timeout', (string) $socketTimeout);
        }
    }

    private function observe(string $name, callable $probe): void
    {
        try {
            $probe();
            $this->store($name, CheckState::Healthy);
        } catch (Throwable $exception) {
            report($exception);
            $this->store($name, CheckState::Unavailable);
        }
    }

    private function store(string $name, CheckState $state): void
    {
        $now = now();
        $values = ['state' => $state->value, 'checked_at' => $now];

        if ($state === CheckState::Healthy) {
            $values['succeeded_at'] = $now;
        }

        rescue(fn () => DiagnosticObservation::query()->updateOrCreate(['name' => $name], $values), report: false);
    }

    private function probed(Collection $observations, string $name, string $label, array $text): array
    {
        $observation = $observations->get($name);
        $state = CheckState::tryFrom((string) $observation?->state) ?? CheckState::Missing;

        return $this->check(
            $label,
            $state,
            $text[$state->value] ?? 'This check has not run yet. Select Recheck to run it.',
            $state === CheckState::Healthy ? null : $text['hint'] ?? null,
            $observation?->succeeded_at,
            $observation?->checked_at,
        );
    }

    private function databaseText(): array
    {
        return [
            'healthy' => 'Openlink can query PostgreSQL.',
            'unavailable' => 'Openlink could not query PostgreSQL within '.Probes::TIMEOUT_SECONDS.' seconds.',
            'hint' => 'Check that PostgreSQL is running and that the DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, and DB_PASSWORD environment variables match it. Details are in the application log.',
        ];
    }

    private function storageText(): array
    {
        return [
            'healthy' => 'Openlink can write to its storage directory.',
            'unavailable' => 'Openlink could not write a test file to its storage directory.',
            'disabled' => 'The default storage disk is not local, so Openlink does not probe it. Check it with your storage provider.',
            'hint' => $this->probes->storageIsLocal() ? 'Make the storage directory writable by the application user and persist it as a volume shared by the app, worker, and scheduler containers.' : null,
        ];
    }

    private function redis(Collection $observations): array
    {
        $uses = $this->redisInUse();

        if ($uses->isEmpty()) {
            return $this->check(
                'Redis',
                CheckState::Disabled,
                'No cache, queue, or session store uses Redis on this installation.',
                null,
                null,
                now(),
            );
        }

        return $this->probed($observations, 'redis', 'Redis', [
            'healthy' => 'Openlink can reach Redis, used for '.$uses->join(', ', ' and ').'.',
            'unavailable' => 'Openlink could not reach Redis within '.Probes::TIMEOUT_SECONDS.' seconds. Redis is used for '.$uses->join(', ', ' and ').'.',
            'hint' => 'Check that Redis is running and that REDIS_HOST, REDIS_PORT, and REDIS_PASSWORD match it. Details are in the application log.',
        ]);
    }

    private function redisInUse(): Collection
    {
        $cache = config('cache.default');
        $queue = config('queue.default');

        return collect([
            'the cache' => config("cache.stores.{$cache}.driver") === 'redis',
            'the queue' => config("queue.connections.{$queue}.driver") === 'redis',
            'sessions' => config('session.driver') === 'redis',
        ])->filter()->keys();
    }

    private function worker(?DiagnosticObservation $heartbeat): array
    {
        $required = config('openlink.analytics.via_queue')
            ? 'Analytics recording in queue mode needs a running worker.'
            : 'No feature needs a worker right now: analytics are recorded after the response.';

        if ($this->queueRunsInline()) {
            return $this->check(
                'Queue worker',
                CheckState::Disabled,
                'QUEUE_CONNECTION is sync, so queued jobs run inside the request and no worker is used.',
                null,
                $heartbeat?->succeeded_at,
                now(),
            );
        }

        $state = $this->heartbeats->freshness($heartbeat?->succeeded_at);

        if ($state !== CheckState::Healthy && ! config('openlink.analytics.via_queue')) {
            return $this->check(
                'Queue worker',
                CheckState::Disabled,
                ($state === CheckState::Stale
                    ? 'No queue worker has reported a heartbeat recently, but none is required. '
                    : 'No queue worker has reported a heartbeat, but none is required. ').$required,
                null,
                $heartbeat?->succeeded_at,
                now(),
            );
        }

        return $this->check(
            'Queue worker',
            $state,
            match ($state) {
                CheckState::Healthy => "A queue worker is running. {$required}",
                CheckState::Stale => "The last queue worker heartbeat is older than {$this->heartbeats->staleAfterMinutes()} minutes. {$required}",
                default => "No queue worker has reported a heartbeat yet. {$required}",
            },
            match ($state) {
                CheckState::Healthy => null,
                CheckState::Stale => 'Check that the queue worker process is still running and can reach PostgreSQL, then restart it through your deployment platform. In the Docker Compose example, check the worker service logs.',
                default => 'Start a queue worker with php artisan queue:work under a process supervisor. In the Docker Compose example, start the worker service.',
            },
            $heartbeat?->succeeded_at,
            now(),
        );
    }

    private function scheduler(?DiagnosticObservation $heartbeat): array
    {
        $state = $this->heartbeats->freshness($heartbeat?->succeeded_at);
        $duty = 'The scheduler prunes analytics past retention and rechecks pending domains.';

        return $this->check(
            'Scheduler',
            $state,
            match ($state) {
                CheckState::Healthy => "The scheduler is running. {$duty}",
                CheckState::Stale => "The last scheduler heartbeat is older than {$this->heartbeats->staleAfterMinutes()} minutes. {$duty}",
                default => "The scheduler has not reported a heartbeat yet. {$duty}",
            },
            match ($state) {
                CheckState::Healthy => null,
                CheckState::Stale => 'Check that the scheduler process is still running and can reach PostgreSQL, then restart it through your deployment platform. In the Docker Compose example, check the scheduler service logs.',
                default => 'Run php artisan schedule:work as a long-running process, or call php artisan schedule:run from cron every minute. In the Docker Compose example, start the scheduler service.',
            },
            $heartbeat?->succeeded_at,
            now(),
        );
    }

    private function mail(): array
    {
        $configuration = $this->mailConfiguration();

        return $this->check(
            'Mail configuration',
            $configuration['state'],
            $configuration['summary'],
            $configuration['state'] === CheckState::Healthy
                ? null
                : 'Set MAIL_MAILER, MAIL_FROM_ADDRESS, and the matching mail credentials in the deployment environment, then restart Openlink.',
            null,
            now(),
        );
    }

    private function mailConfiguration(): array
    {
        $mailer = (string) config('mail.default');
        $transport = config("mail.mailers.{$mailer}.transport");

        $missing = match ($transport) {
            null => 'MAIL_MAILER names a mailer that does not exist.',
            'log' => 'Emails are written to the application log instead of being sent.',
            'array' => 'Emails are kept in memory instead of being sent.',
            default => $this->missingMailSetting($mailer, (string) $transport),
        };

        if ($missing === null && blank(config('mail.from.address'))) {
            $missing = 'No sender address is set.';
        }

        if ($missing !== null) {
            return ['state' => CheckState::NotConfigured, 'summary' => $missing];
        }

        return [
            'state' => CheckState::Healthy,
            'summary' => "Openlink is configured to send email with the {$transport} transport. This does not prove that the mail server accepts messages; send a test email to check.",
        ];
    }

    private function missingMailSetting(string $mailer, string $transport): ?string
    {
        $required = match ($transport) {
            'smtp' => [filled(config("mail.mailers.{$mailer}.url")) ? "mail.mailers.{$mailer}.url" : "mail.mailers.{$mailer}.host"],
            'sendmail' => ["mail.mailers.{$mailer}.path"],
            'cloudflare' => ['services.cloudflare.account_id', 'services.cloudflare.key'],
            'postmark' => ['services.postmark.key'],
            'resend' => ['services.resend.key'],
            'ses', 'ses-v2' => ['services.ses.key', 'services.ses.secret'],
            default => [],
        };

        return collect($required)->contains(fn (string $key) => blank(config($key)))
            ? "The {$transport} transport is selected but its connection settings are incomplete."
            : null;
    }

    private function analytics(CheckState $worker): array
    {
        if (! config('openlink.analytics.via_queue')) {
            return [
                ...$this->check(
                    'Analytics recording',
                    CheckState::Healthy,
                    'After-response mode: each visit and scan is written after its redirect is sent. No queue worker is needed.',
                    null,
                    null,
                    now(),
                ),
                'mode' => 'after_response',
            ];
        }

        [$state, $summary, $hint] = match (true) {
            $this->queueRunsInline() => [
                CheckState::NotConfigured,
                'Queue mode is on, but QUEUE_CONNECTION is sync, so events are written before each redirect finishes.',
                'Set QUEUE_CONNECTION=redis and run a queue worker, or remove OPENLINK_ANALYTICS_VIA_QUEUE to record after the response.',
            ],
            $worker === CheckState::Healthy => [
                CheckState::Healthy,
                'Queue mode: a queue worker writes visits and scans from the queue.',
                null,
            ],
            default => [
                CheckState::Unavailable,
                'Queue mode: visits and scans wait in the queue and are not recorded until a queue worker runs.',
                'Start a queue worker, or remove OPENLINK_ANALYTICS_VIA_QUEUE to record after the response without a worker.',
            ],
        };

        return [...$this->check('Analytics recording', $state, $summary, $hint, null, now()), 'mode' => 'queue'];
    }

    private function mailTest(?DiagnosticObservation $observation): ?array
    {
        if ($observation === null) {
            return null;
        }

        $accepted = $observation->state === CheckState::Healthy->value;

        return [
            'state' => $accepted ? CheckState::Healthy->value : CheckState::Unavailable->value,
            'summary' => $accepted
                ? 'The mail server accepted the last test email. This does not confirm delivery to the inbox.'
                : 'The last test email could not be handed to the mail server. Details are in the application log.',
            'checkedAt' => $observation->checked_at?->toIso8601String(),
        ];
    }

    private function queueRunsInline(): bool
    {
        $queue = config('queue.default');

        return config("queue.connections.{$queue}.driver") === 'sync';
    }

    private function check(
        string $label,
        CheckState $state,
        string $summary,
        ?string $hint,
        ?CarbonInterface $observedAt,
        ?CarbonInterface $checkedAt,
    ): array {
        return [
            'label' => $label,
            'state' => $state->value,
            'summary' => $summary,
            'hint' => $hint,
            'observedAt' => $observedAt?->toIso8601String(),
            'checkedAt' => $checkedAt?->toIso8601String(),
        ];
    }
}
