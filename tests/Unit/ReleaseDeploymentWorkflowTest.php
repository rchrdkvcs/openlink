<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ReleaseDeploymentWorkflowTest extends TestCase
{
    public function test_release_workflow_delegates_deployment_after_a_release_is_published(): void
    {
        $workflow = $this->workflow('release.yml');

        $this->assertStringContainsString('released: ${{ steps.semantic_release.outputs.released }}', $workflow);
        $this->assertStringContainsString("if: needs.release.outputs.released == 'true'", $workflow);
        $this->assertStringContainsString('uses: ./.github/workflows/deploy.yml', $workflow);
        $this->assertStringContainsString('COOLIFY_WEBHOOK: ${{ secrets.COOLIFY_WEBHOOK }}', $workflow);
        $this->assertStringContainsString('COOLIFY_TOKEN: ${{ secrets.COOLIFY_TOKEN }}', $workflow);
        $this->assertStringNotContainsString('curl ', $workflow);
    }

    public function test_deployment_workflow_is_reusable_and_manually_dispatchable(): void
    {
        $workflow = $this->workflow('deploy.yml');

        $this->assertStringContainsString('workflow_call:', $workflow);
        $this->assertStringContainsString('workflow_dispatch:', $workflow);
        $this->assertStringContainsString('COOLIFY_WEBHOOK: ${{ secrets.COOLIFY_WEBHOOK }}', $workflow);
        $this->assertStringContainsString('COOLIFY_TOKEN: ${{ secrets.COOLIFY_TOKEN }}', $workflow);
        $this->assertStringContainsString('Authorization: Bearer $COOLIFY_TOKEN', $workflow);
    }

    private function workflow(string $name): string
    {
        return file_get_contents(dirname(__DIR__, 2).'/.github/workflows/'.$name);
    }
}
