<?php

namespace App\Console\Commands;

use App\Actions\Domains\RunDomainChecks;
use App\Models\Domain;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('openlink:verify-pending-domains {--limit=50}')]
#[Description('Recheck workspace domains awaiting DNS verification or pointing')]
class VerifyPendingDomains extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(RunDomainChecks $checks): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $domains = Domain::query()
            ->whereIn('status', [Domain::STATUS_PENDING, Domain::STATUS_FAILED, Domain::STATUS_OWNERSHIP_VERIFIED])
            ->whereNull('disabled_at')
            ->oldest('last_checked_at')
            ->limit($limit)
            ->get();

        $domains->each(fn (Domain $domain) => $checks->handle($domain));

        $this->info("Checked {$domains->count()} domains.");

        return self::SUCCESS;
    }
}
