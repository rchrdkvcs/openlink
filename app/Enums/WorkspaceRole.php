<?php

namespace App\Enums;

use Illuminate\Validation\ValidationException;

enum WorkspaceRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Editor = 'editor';
    case Viewer = 'viewer';

    public function canEditContent(): bool
    {
        return $this !== self::Viewer;
    }

    public function canManageWorkspace(): bool
    {
        return $this === self::Owner || $this === self::Admin;
    }

    public function ownsWorkspace(): bool
    {
        return $this === self::Owner;
    }

    public function isAssignable(): bool
    {
        return $this !== self::Owner;
    }

    public function ensureAssignable(): self
    {
        if (! $this->isAssignable()) {
            throw ValidationException::withMessages(['role' => __('validation.in', ['attribute' => 'role'])]);
        }

        return $this;
    }

    public static function assignableValues(): array
    {
        return array_values(array_map(
            fn (self $role) => $role->value,
            array_filter(self::cases(), fn (self $role) => $role->isAssignable()),
        ));
    }
}
