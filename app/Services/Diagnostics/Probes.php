<?php

namespace App\Services\Diagnostics;

use Illuminate\Redis\RedisManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;

class Probes
{
    public const TIMEOUT_SECONDS = 2;

    private const CONNECTION = 'openlink-diagnostics';

    public function __construct(private readonly RedisManager $redis) {}

    public function database(): void
    {
        $default = config('database.default');
        $settings = (array) config("database.connections.{$default}");

        config(['database.connections.'.self::CONNECTION => [
            ...$settings,
            'options' => [...($settings['options'] ?? []), PDO::ATTR_TIMEOUT => self::TIMEOUT_SECONDS],
        ]]);

        try {
            $connection = DB::connection(self::CONNECTION);
            $connection->statement(sprintf("set statement_timeout = '%ds'", self::TIMEOUT_SECONDS));
            $connection->select('select 1');
        } finally {
            DB::purge(self::CONNECTION);
        }
    }

    public function redis(): void
    {
        config(['database.redis.'.self::CONNECTION => [
            ...(array) config('database.redis.default'),
            'timeout' => self::TIMEOUT_SECONDS,
            'read_timeout' => self::TIMEOUT_SECONDS,
            'max_retries' => 0,
        ]]);

        try {
            $this->redis->connection(self::CONNECTION)->ping();
        } finally {
            $this->redis->purge(self::CONNECTION);
        }
    }

    public function storageIsLocal(): bool
    {
        $disk = config('filesystems.default');

        return config("filesystems.disks.{$disk}.driver") === 'local';
    }

    public function storage(): void
    {
        $storage = Storage::disk(config('filesystems.default'));
        $path = '.openlink-diagnostics-'.Str::random(16);

        try {
            if (! $storage->put($path, 'ok') || $storage->get($path) !== 'ok') {
                throw new RuntimeException('The default storage disk is not writable.');
            }
        } finally {
            rescue(fn () => $storage->delete($path), report: false);
        }
    }
}
