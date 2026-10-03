<?php

namespace App\Http\Requests\ShortLinks;

use App\Models\ShortLink;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveShortLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->shortLink());
    }

    public function rules(): array
    {
        return [
            'folder_id' => ['nullable', Rule::exists('folders', 'id')->where('workspace_id', $this->shortLink()->workspace_id)],
        ];
    }

    public function folderId(): ?int
    {
        $folderId = $this->validated('folder_id');

        return filled($folderId) ? (int) $folderId : null;
    }

    private function shortLink(): ShortLink
    {
        return $this->route('shortLink');
    }
}
