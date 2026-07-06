<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Workspace;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DomainManager
{
    public function create(Workspace $workspace, string $hostname): Domain
    {
        $hostname = strtolower(preg_replace('/^https?:\/\//', '', trim($hostname)));
        $hostname = trim($hostname, '/');

        return Domain::create([
            'workspace_id' => $workspace->id,
            'hostname' => $hostname,
            'status' => Domain::STATUS_PENDING,
            'verification_token' => Str::random(40),
        ]);
    }

    public function disable(Domain $domain): Domain
    {
        $domain->update([
            'status' => Domain::STATUS_DISABLED,
            'disabled_at' => now(),
        ]);

        return $domain;
    }

    public function transfer(Domain $domain, Workspace $from, Workspace $target): Domain
    {
        if ($target->id === $from->id) {
            return $domain;
        }

        if ($domain->shortLinks()->exists()) {
            throw ValidationException::withMessages([
                'workspace_id' => 'Move or delete links using this domain before transferring it.',
            ]);
        }

        if ((int) $from->preferred_domain_id === $domain->id) {
            $from->forceFill(['preferred_domain_id' => null])->save();
        }

        $domain->update(['workspace_id' => $target->id]);

        return $domain;
    }
}
