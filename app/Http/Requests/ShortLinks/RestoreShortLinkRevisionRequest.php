<?php

namespace App\Http\Requests\ShortLinks;

use App\Actions\ShortLinks\RevisionPrecondition;
use App\Models\ShortLink;
use App\Models\ShortLinkRevision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RestoreShortLinkRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->shortLink());
    }

    public function rules(): array
    {
        return [
            'expected_revision' => [Rule::requiredIf(! $this->hasHeader('If-Match')), 'integer', 'min:0'],
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
        $fromBody = $this->validated('expected_revision');

        return (int) $this->container->make(RevisionPrecondition::class)
            ->expected($this, $this->shortLink(), $fromBody === null ? null : (int) $fromBody);
    }
}
