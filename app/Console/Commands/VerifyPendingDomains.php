<?php

namespace App\Console\Commands;

use App\Actions\Domains\DomainLifecycle;
use App\Enums\DomainStatus;
use App\Enums\EdgeStatus;
use App\Models\Domain;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('openlink:verify-pending-domains {--limit=50} {--issuing : Only recheck domains whose HTTPS certificate is being issued} {--all : Recheck every workspace domain, including active ones, and list those that do not reach the domain edge}')]
#[Description('Recheck workspace domains awaiting DNS verification, pointing or HTTPS')]
class VerifyPendingDomains extends Command
{
    public function handle(DomainLifecycle $lifecycle): int
    {
        $released = $lifecycle->releaseAbandoned();
        $checked = 0;
        $unreached = [];

        foreach ($this->domains() as $domain) {
            $lifecycle->check($domain);
            $checked++;

            if ($domain->edge_status !== EdgeStatus::Connected) {
                $unreached[] = [$domain->hostname, $domain->status->value, $domain->workspace?->name, $domain->workspace?->owner?->email];
            }
        }

        $this->info("Checked {$checked} domains.");

        if ($released > 0) {
            $this->info("Released {$released} abandoned domains from the domain edge.");
        }

        if ($this->option('all') && $unreached !== []) {
            $this->warn('These domains do not reach the domain edge yet. Ask their owners to update their DNS record:');
            $this->table(['Domain', 'Status', 'Workspace', 'Owner email'], $unreached);
        }

        return self::SUCCESS;
    }

    private function domains(): iterable
    {
        $query = Domain::query()->with('workspace.owner');
        $limit = max(1, (int) $this->option('limit'));

        return match (true) {
            (bool) $this->option('all') => $query
                ->whereNotNull('workspace_id')
                ->whereNull('disabled_at')
                ->where(fn (Builder $query) => $query
                    ->where('status', DomainStatus::Active)
                    ->orWhereIn('id', Domain::query()->awaitingChecks()->select('id')))
                ->lazyById(),
            (bool) $this->option('issuing') => $query
                ->awaitingChecks()
                ->where('edge_status', EdgeStatus::Issuing)
                ->oldest('last_checked_at')
                ->limit($limit)
                ->get(),
            default => $query
                ->awaitingChecks()
                ->oldest('last_checked_at')
                ->limit($limit)
                ->get(),
        };
    }
}
