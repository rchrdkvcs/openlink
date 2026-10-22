<?php

namespace App\Http\Requests\LinkPages;

use App\Models\LinkPage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateLinkPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->linkPage());
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'path' => ['required', 'string', 'max:64'],
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:280'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096'],
            'remove_logo' => ['nullable', 'boolean'],
            'display' => ['sometimes', 'required', 'array:show_icons,show_labels,show_urls'],
            'display.show_icons' => ['required_with:display', 'boolean'],
            'display.show_labels' => ['required_with:display', 'boolean'],
            'display.show_urls' => ['required_with:display', 'boolean'],
            'items' => ['nullable', 'array', 'max:'.LinkPage::MAX_ITEMS],
            'items.*.short_link_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('short_links', 'id')->where('workspace_id', $this->linkPage()->workspace_id),
            ],
            'items.*.label' => ['nullable', 'string', 'max:80'],
        ];
    }

    private function linkPage(): LinkPage
    {
        return $this->route('linkPage');
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->has('display')) {
                return;
            }

            if (! collect($this->input('display'))->contains(fn ($value) => (bool) $value)) {
                $validator->errors()->add('display', 'Show at least icons, labels, or short URLs.');
            }
        }];
    }
}
