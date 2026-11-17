<?php

namespace App\Console\Commands;

use App\Actions\Domains\DomainLifecycle;
use App\Enums\DomainStatus;
use App\Models\Domain;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('openlink:verify-pending-domains {--limit=50} {--all : Also recheck active domains, for example after changing the domain edge}')]
#[Description('Recheck workspace domains awaiting DNS verification or pointing')]
class VerifyPendingDomains extends Command
{
    public function handle(DomainLifecycle $lifecycle): int
    {
        $all = (bool) $this->option('all');
        $statuses = $all ? [...DomainStatus::awaitingChecks(), DomainStatus::Active] : DomainStatus::awaitingChecks();
        $query = Domain::query()
            ->whereNotNull('workspace_id')
            ->whereIn('status', $statuses)
            ->whereNull('disabled_at');

        $startedAt = now()->startOfSecond();
        $checked = 0;
        $waiting = [];

        foreach ($all ? $query->lazyById() : $query->oldest('last_checked_at')->limit(max(1, (int) $this->option('limit')))->get() as $domain) {
            $lifecycle->check($domain);
            $checked++;

            if ($all && ($domain->dns_pointed_at === null || $domain->dns_pointed_at->lt($startedAt))) {
                $waiting[] = [$domain->hostname, $domain->status->value];
            }
        }

        $this->info("Checked {$checked} domains.");

        if ($waiting !== []) {
            $this->warn('These domains do not reach the domain edge yet:');
            $this->table(['Domain', 'Status'], $waiting);
        }

        return self::SUCCESS;
    }
}
