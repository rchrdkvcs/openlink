<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class CiWorkflowTest extends TestCase
{
    public function test_ci_runs_on_the_default_branch(): void
    {
        $workflow = file_get_contents(
            dirname(__DIR__, 2).'/.github/workflows/ci.yml',
        );

        $this->assertStringContainsString(
            "pull_request:\n    branches:\n      - main",
            $workflow,
        );
        $this->assertStringContainsString(
            "push:\n    branches:\n      - main",
            $workflow,
        );
        $this->assertStringNotContainsString('- develop', $workflow);
        $this->assertStringNotContainsString('- master', $workflow);
    }
}
