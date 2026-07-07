<?php

namespace App\Actions\Domains;

use App\Models\Domain;

class RunDomainChecks
{
    public function __construct(
        private readonly VerifyDomain $verifier,
        private readonly CheckDomainPointing $pointing,
    ) {}

    public function handle(Domain $domain): Domain
    {
        $domain = $this->verifier->handle($domain);

        return $this->pointing->handle($domain);
    }
}
