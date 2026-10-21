<?php

namespace App\Http\Requests\ShortLinks;

use App\Actions\ShortLinks\BulkShortLinkMutation;
use App\Actions\Workspaces\CurrentWorkspace;
use App\Enums\BulkLinkAction;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkShortLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->workspace();

        return $workspace !== null && $this->user()->can('editContent', $workspace);
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::enum(BulkLinkAction::class)],
            'ids' => ['required', 'array', 'min:1', 'max:'.BulkShortLinkMutation::MAX_LINKS],
            'ids.*' => ['integer', 'distinct'],
            'folder_id' => [
                'exclude_unless:action,'.BulkLinkAction::Move->value,
                'present',
                'nullable',
                Rule::exists('folders', 'id')->where('workspace_id', $this->workspace()?->id),
            ],
            'tags' => [
                Rule::excludeIf(! $this->bulkActionOrNull()?->needsTags()),
                'required',
                'array',
                'min:1',
                'max:20',
            ],
            'tags.*' => ['required', 'string', 'max:80', 'distinct'],
        ];
    }

    public function workspace(): ?Workspace
    {
        return $this->container->make(CurrentWorkspace::class)->get();
    }

    public function bulkAction(): BulkLinkAction
    {
        return BulkLinkAction::from($this->validated('action'));
    }

    public function ids(): array
    {
        return array_map('intval', $this->validated('ids'));
    }

    public function folderId(): ?int
    {
        $folderId = $this->validated('folder_id');

        return filled($folderId) ? (int) $folderId : null;
    }

    public function tags(): array
    {
        return $this->validated('tags') ?? [];
    }

    private function bulkActionOrNull(): ?BulkLinkAction
    {
        return BulkLinkAction::tryFrom((string) $this->input('action'));
    }
}
