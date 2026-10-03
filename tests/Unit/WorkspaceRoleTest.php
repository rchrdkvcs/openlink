<?php

namespace Tests\Unit;

use App\Enums\WorkspaceRole;
use PHPUnit\Framework\TestCase;

class WorkspaceRoleTest extends TestCase
{
    public function test_capability_table(): void
    {
        $table = array_map(fn (WorkspaceRole $role) => [
            $role->value,
            $role->canEditContent(),
            $role->canManageWorkspace(),
            $role->ownsWorkspace(),
            $role->isAssignable(),
        ], WorkspaceRole::cases());

        $this->assertSame([
            ['owner', true, true, true, false],
            ['admin', true, true, false, true],
            ['editor', true, false, false, true],
            ['viewer', false, false, false, true],
        ], $table);
    }

    public function test_assignable_values_exclude_owner(): void
    {
        $this->assertSame(['admin', 'editor', 'viewer'], WorkspaceRole::assignableValues());
    }
}
