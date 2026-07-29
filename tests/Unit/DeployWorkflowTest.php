<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class DeployWorkflowTest extends TestCase
{
    public function test_a_published_stable_release_triggers_a_coolify_deployment(): void
    {
        $workflow = file_get_contents(
            dirname(__DIR__, 2).'/.github/workflows/deploy.yml',
        );

        $this->assertStringContainsString('release:', $workflow);
        $this->assertStringContainsString('types: [published]', $workflow);
        $this->assertStringContainsString(
            'if: github.event.release.prerelease == false',
            $workflow,
        );
        $this->assertStringContainsString(
            'gh api "repos/$GITHUB_REPOSITORY/releases/latest"',
            $workflow,
        );
        $this->assertStringContainsString(
            'if [ "$RELEASE_ID" != "$latest_release_id" ]',
            $workflow,
        );
        $this->assertStringContainsString(
            'cancel-in-progress: false',
            $workflow,
        );
        $this->assertStringContainsString(
            'COOLIFY_WEBHOOK: ${{ secrets.COOLIFY_WEBHOOK }}',
            $workflow,
        );
        $this->assertStringContainsString(
            'COOLIFY_TOKEN: ${{ secrets.COOLIFY_TOKEN }}',
            $workflow,
        );
        $this->assertStringContainsString(
            'Authorization: Bearer $COOLIFY_TOKEN',
            $workflow,
        );
        $this->assertStringNotContainsString('workflow_call:', $workflow);
        $this->assertStringNotContainsString('workflow_dispatch:', $workflow);
    }
}
