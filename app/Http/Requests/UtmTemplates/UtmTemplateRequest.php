<?php

namespace App\Http\Requests\UtmTemplates;

use App\Actions\Workspaces\CurrentWorkspace;
use App\Models\UtmTemplate;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UtmTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $template = $this->template();

        if ($template) {
            return $this->user()->can('update', $template);
        }

        $workspace = $this->workspace();

        return $workspace !== null && $this->user()->can('manage', $workspace);
    }

    public function workspace(): ?Workspace
    {
        return $this->template()?->workspace ?? $this->container->make(CurrentWorkspace::class)->get();
    }

    public function rules(): array
    {
        $template = $this->template();

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('utm_templates', 'name')
                    ->where('workspace_id', $this->workspace()?->id)
                    ->ignore($template),
            ],
            ...array_fill_keys(UtmTemplate::PARAMETERS, ['nullable', 'string', 'max:250']),
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (collect(UtmTemplate::PARAMETERS)->every(fn (string $key) => blank($this->input($key)))) {
                    $validator->errors()->add('parameters', 'Add at least one UTM value.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'utm_source' => 'source',
            'utm_medium' => 'medium',
            'utm_campaign' => 'campaign',
            'utm_term' => 'term',
            'utm_content' => 'content',
        ];
    }

    public function templateAttributes(): array
    {
        return [
            'name' => $this->validated('name'),
            ...collect(UtmTemplate::PARAMETERS)
                ->mapWithKeys(fn (string $key) => [$key => filled($this->validated($key)) ? $this->validated($key) : null])
                ->all(),
        ];
    }

    private function template(): ?UtmTemplate
    {
        $template = $this->route('utmTemplate');

        return $template instanceof UtmTemplate ? $template : null;
    }
}
