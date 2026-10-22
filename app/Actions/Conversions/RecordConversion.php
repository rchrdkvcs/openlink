<?php

namespace App\Actions\Conversions;

use App\Models\ConversionAttribution;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Conversions\AttributionTokens;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Validation\ValidationException;

class RecordConversion
{
    public const RECORDED = 'recorded';

    public const DUPLICATE = 'duplicate';

    public function __construct(private readonly Gate $gate) {}

    public function handle(User $user, Workspace $workspace, string $token): array
    {
        $this->gate->forUser($user)->authorize('editContent', $workspace);

        $attribution = $this->eligibleAttribution($workspace, $token);

        if ($attribution->isConverted()) {
            return [self::DUPLICATE, $attribution];
        }

        $recorded = ConversionAttribution::query()
            ->whereKey($attribution->id)
            ->whereNull('converted_at')
            ->where('expires_at', '>', now())
            ->update(['converted_at' => now()]);

        $attribution->refresh();

        if (! $attribution->isConverted()) {
            throw $this->invalidToken();
        }

        return [$recorded ? self::RECORDED : self::DUPLICATE, $attribution];
    }

    private function eligibleAttribution(Workspace $workspace, string $token): ConversionAttribution
    {
        $attribution = ConversionAttribution::query()
            ->with('shortLink')
            ->where('token_hash', AttributionTokens::hash($token))
            ->where('workspace_id', $workspace->id)
            ->first();

        $eligible = $attribution
            && $attribution->shortLink?->tracksConversions()
            && ($attribution->isConverted() || $attribution->expires_at->isFuture());

        if (! $eligible) {
            throw $this->invalidToken();
        }

        return $attribution;
    }

    private function invalidToken(): ValidationException
    {
        return ValidationException::withMessages(['token' => 'The attribution token is invalid or has expired.']);
    }
}
