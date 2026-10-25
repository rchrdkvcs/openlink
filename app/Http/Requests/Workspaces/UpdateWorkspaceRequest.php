<?php

namespace App\Http\Requests\Workspaces;

class UpdateWorkspaceRequest extends StoreWorkspaceRequest
{
    public function rules(): array
    {
        $rules = [
            ...parent::rules(),
            'preferred_domain_id' => ['nullable', 'integer'],
        ];

        if ($this->routeIs('api.*')) {
            array_unshift($rules['name'], 'sometimes');
        }

        return $rules;
    }
}
