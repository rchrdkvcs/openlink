<?php

namespace App\Http\Requests\ShortLinks;

use App\Models\ShortLink;
use App\Models\ShortLinkRevision;
use Illuminate\Foundation\Http\FormRequest;

class RestoreShortLinkRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->shortLink());
    }

    public function rules(): array
    {
        return [
            'expected_revision' => ['required', 'integer', 'min:0'],
        ];
    }

    public function shortLink(): ShortLink
    {
        return $this->route('shortLink');
    }

    public function revision(): ShortLinkRevision
    {
        return $this->shortLink()->findRevision((int) $this->route('revision'));
    }

    public function expectedRevision(): int
    {
        return (int) $this->validated('expected_revision');
    }
}
