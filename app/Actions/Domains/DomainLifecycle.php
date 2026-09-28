<?php

namespace App\Actions\Domains;

use App\Models\Domain;
use Illuminate\Http\Request;

class DomainLifecycle
{
    public function __construct(
        private readonly VerifyDomain $verifier,
        private readonly CheckDomainPointing $pointing,
    ) {}

    public function check(Domain $domain): Domain
    {
        $domain = $this->verifier->handle($domain);

        return $this->pointing->handle($domain);
    }

    public function activateOnObservedTraffic(Request $request, Domain $domain): void
    {
        if ($domain->status === Domain::STATUS_OWNERSHIP_VERIFIED
            && $domain->disabled_at === null
            && strcasecmp($request->getHost(), $domain->hostname) === 0) {
            $domain->activate();
        }
    }
}
