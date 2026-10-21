<?php

namespace App\Http\Requests\ShortLinks;

use App\Actions\Workspaces\CurrentWorkspace;
use App\Models\Workspace;
use App\Services\ShortLinks\Csv\LinkCsvFile;
use App\Services\ShortLinks\Csv\LinkCsvReader;
use Illuminate\Foundation\Http\FormRequest;

class ImportShortLinksRequest extends FormRequest
{
    public const MAX_KILOBYTES = 1024;

    public function authorize(): bool
    {
        $workspace = $this->workspace();

        return $workspace !== null && $this->user()->can('editContent', $workspace);
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'extensions:csv,txt,tsv', 'max:'.self::MAX_KILOBYTES],
            'mapping' => ['nullable', 'array'],
            'mapping.*' => ['nullable', 'integer', 'min:0'],
            'domain_id' => ['nullable', 'integer'],
            'dry_run' => ['sometimes', 'boolean'],
        ];
    }

    public function workspace(): ?Workspace
    {
        return $this->container->make(CurrentWorkspace::class)->get();
    }

    public function csv(): LinkCsvFile
    {
        return $this->container->make(LinkCsvReader::class)->read($this->file('file')->get());
    }

    public function mapping(): ?array
    {
        return $this->validated('mapping');
    }

    public function domainId(): ?int
    {
        return $this->filled('domain_id') ? $this->integer('domain_id') : null;
    }
}
