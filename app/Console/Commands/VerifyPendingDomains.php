<?php

namespace App\Console\Commands;

use App\Actions\Domains\DomainLifecycle;
use App\Enums\DomainStatus;
use App\Models\Domain;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('openlink:verify-pending-domains {--limit=50}')]
#[Description('Recheck workspace domains awaiting DNS verification or pointing')]
class VerifyPendingDomains extends Command
{
    public function handle(DomainLifecycle $lifecycle): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $domains = Domain::query()
            ->whereIn('status', DomainStatus::awaitingChecks())
            ->whereNull('disabled_at')
            ->oldest('last_checked_at')
            ->limit($limit)
            ->get();

        $domains->each(fn (Domain $domain) => $lifecycle->check($domain));

        $this->info("Checked {$domains->count()} domains.");

        return self::SUCCESS;
    }
}
