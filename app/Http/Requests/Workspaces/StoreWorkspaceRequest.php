<?php

namespace App\Http\Requests\Workspaces;

use App\Models\WorkspaceAppearance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkspaceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'icon' => ['nullable', 'string', Rule::in(WorkspaceAppearance::ICONS)],
            'color' => ['nullable', 'string', Rule::in(WorkspaceAppearance::COLORS)],
        ];
    }
}
