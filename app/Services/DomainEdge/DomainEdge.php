<?php

namespace App\Services\DomainEdge;

use App\Models\Domain;

interface DomainEdge
{
    public function connect(Domain $domain): EdgeConnection;

    public function release(Domain $domain): void;
}
