<?php

namespace App\Http\Requests\Workspaces;

class UpdateWorkspaceRequest extends StoreWorkspaceRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'preferred_domain_id' => ['nullable', 'integer'],
        ];
    }
}
