<?php

namespace App\Console\Commands;

use App\Models\WebhookDelivery;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('openlink:prune-webhook-deliveries')]
#[Description('Prune webhook delivery history older than the configured retention window')]
class PruneWebhookDeliveries extends Command
{
    private const CHUNK_SIZE = 1000;

    public function handle(): int
    {
        $days = (int) config('openlink.webhooks.retention_days');
        $cutoff = now()->subDays($days);
        $deleted = 0;

        do {
            $ids = WebhookDelivery::query()
                ->where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->limit(self::CHUNK_SIZE)
                ->pluck('id');

            $deleted += $ids->isEmpty() ? 0 : WebhookDelivery::query()->whereKey($ids)->delete();
        } while ($ids->count() === self::CHUNK_SIZE);

        $this->info("Pruned {$deleted} webhook deliveries older than {$days} days.");

        return self::SUCCESS;
    }
}
