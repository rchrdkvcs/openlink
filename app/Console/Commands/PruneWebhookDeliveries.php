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
    public function handle(): int
    {
        $days = (int) config('openlink.webhooks.retention_days');
        $deleted = WebhookDelivery::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        $this->info("Pruned {$deleted} webhook deliveries older than {$days} days.");

        return self::SUCCESS;
    }
}
