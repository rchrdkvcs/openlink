<?php

namespace App\Http\Requests\Members;

use App\Enums\WorkspaceRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRoleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in(WorkspaceRole::assignableValues())],
        ];
    }

    public function role(): WorkspaceRole
    {
        return WorkspaceRole::from($this->validated('role'));
    }
}
