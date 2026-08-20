<?php

namespace App\Actions\BioPages;

use App\Actions\Analytics\RecordAnalytics;
use App\Actions\Domains\DomainLifecycle;
use App\Models\BioPage;
use App\Models\Domain;
use App\Services\Analytics\Outcome;
use Illuminate\Http\Request;

class ResolvePublicBioPage
{
    public function __construct(
        private readonly DomainLifecycle $domains,
        private readonly RecordAnalytics $analytics,
    ) {}

    public function resolve(Request $request, string $slug): ?BioPage
    {
        $domain = Domain::query()->where('hostname', $request->getHost())->first();
        if (! $domain) {
            return null;
        }

        $this->domains->activateOnObservedTraffic($request, $domain);
        if (! $domain->fresh()->isUsable()) {
            return null;
        }

        $bioPage = BioPage::query()
            ->with(['publishedDomain', 'elements'])
            ->where('published_domain_id', $domain->id)
            ->where('published_slug', trim($slug, '/'))
            ->whereNotNull('published_at')
            ->first();

        if ($bioPage) {
            $this->analytics->recordBio($request, $bioPage, RecordAnalytics::METRIC_BIO_VIEW, Outcome::SUCCESS);
        }

        return $bioPage;
    }
}
