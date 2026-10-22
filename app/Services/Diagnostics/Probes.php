<?php

namespace App\Services\Diagnostics;

use Illuminate\Redis\RedisManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class Probes
{
    public const TIMEOUT_SECONDS = 2;

    private const REDIS_CONNECTION = 'openlink-diagnostics';

    public function __construct(private readonly RedisManager $redis) {}

    public function database(): void
    {
        DB::transaction(function () {
            DB::statement(sprintf("set local statement_timeout = '%ds'", self::TIMEOUT_SECONDS));
            DB::select('select 1');
        });
    }

    public function redis(): void
    {
        config(['database.redis.'.self::REDIS_CONNECTION => [
            ...(array) config('database.redis.default'),
            'timeout' => self::TIMEOUT_SECONDS,
            'read_timeout' => self::TIMEOUT_SECONDS,
            'max_retries' => 0,
        ]]);

        try {
            $this->redis->connection(self::REDIS_CONNECTION)->ping();
        } finally {
            $this->redis->purge(self::REDIS_CONNECTION);
        }
    }

    public function storage(): void
    {
        foreach (array_unique([config('filesystems.default'), 'public']) as $disk) {
            $path = '.openlink-diagnostics-'.Str::random(16);
            $storage = Storage::disk($disk);

            if (! $storage->put($path, 'ok') || $storage->get($path) !== 'ok') {
                throw new RuntimeException("Storage disk {$disk} is not writable.");
            }

            $storage->delete($path);
        }
    }
}
