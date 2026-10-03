<?php

namespace App\Http\Requests\InviteLinks;

use App\Enums\WorkspaceRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInviteLinkRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in(WorkspaceRole::assignableValues())],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }

    public function role(): WorkspaceRole
    {
        return WorkspaceRole::from($this->validated('role'));
    }

    public function expiresInDays(): ?int
    {
        return $this->optionalInteger('expires_in_days');
    }

    public function maxUses(): ?int
    {
        return $this->optionalInteger('max_uses');
    }

    private function optionalInteger(string $key): ?int
    {
        $value = $this->validated($key);

        return filled($value) ? (int) $value : null;
    }
}
