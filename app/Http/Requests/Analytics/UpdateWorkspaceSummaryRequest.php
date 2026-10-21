<?php

namespace App\Http\Requests\Analytics;

use App\Actions\Workspaces\CurrentWorkspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkspaceSummaryRequest extends FormRequest
{
    public function rules(): array
    {
        $workspace = app(CurrentWorkspace::class)->get();

        return [
            'is_enabled' => ['required', 'boolean'],
            'timezone' => ['required', 'timezone:all'],
            'recipients' => ['present', 'array', 'max:500', Rule::requiredIf(fn () => $this->boolean('is_enabled'))],
            'recipients.*' => ['integer', 'distinct', Rule::exists('workspace_members', 'id')->where('workspace_id', $workspace?->id)],
        ];
    }

    public function messages(): array
    {
        return [
            'recipients.required' => 'Choose at least one recipient.',
            'recipients.*.exists' => 'Recipients must be current members of this workspace.',
        ];
    }
}
