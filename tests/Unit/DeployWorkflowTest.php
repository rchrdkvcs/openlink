<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class DeployWorkflowTest extends TestCase
{
    public function test_published_releases_trigger_image_publication(): void
    {
        $workflow = file_get_contents(
            dirname(__DIR__, 2).'/.github/workflows/deploy.yml',
        );

        $this->assertStringContainsString('release:', $workflow);
        $this->assertStringContainsString('types: [published]', $workflow);
        $this->assertStringContainsString('packages: write', $workflow);
        $this->assertStringContainsString('registry: ghcr.io', $workflow);
        $this->assertStringContainsString('platform: linux/amd64', $workflow);
        $this->assertStringContainsString('platform: linux/arm64', $workflow);
        $this->assertStringContainsString('cancel-in-progress: false', $workflow);
        $this->assertStringNotContainsString('workflow_call:', $workflow);
        $this->assertStringNotContainsString('workflow_dispatch:', $workflow);
    }

    public function test_prereleases_publish_without_updating_latest(): void
    {
        $workflow = file_get_contents(
            dirname(__DIR__, 2).'/.github/workflows/deploy.yml',
        );

        $this->assertStringContainsString(
            'PRERELEASE: ${{ github.event.release.prerelease }}',
            $workflow,
        );
        $this->assertStringContainsString(
            'if [ "$PRERELEASE" = "true" ]; then'."\n".
            '            echo "publish=true" >> "$GITHUB_OUTPUT"'."\n".
            '            exit 0',
            $workflow,
        );
        $this->assertStringNotContainsString(
            'if: github.event.release.prerelease == false',
            $workflow,
        );
        $this->assertStringContainsString(
            'gh api "repos/$GITHUB_REPOSITORY/releases/latest"',
            $workflow,
        );
        $this->assertStringContainsString(
            'if [ "$RELEASE_ID" = "$latest_release_id" ]; then'."\n".
            '            echo "publish=true" >> "$GITHUB_OUTPUT"'."\n".
            '            echo "latest=true" >> "$GITHUB_OUTPUT"',
            $workflow,
        );
        $this->assertStringContainsString(
            'PUBLISH_LATEST: ${{ needs.check-release.outputs.latest }}',
            $workflow,
        );
        $this->assertStringContainsString('tags=(--tag "$IMAGE:$TAG")', $workflow);
        $this->assertStringContainsString(
            'if [ "$PUBLISH_LATEST" = "true" ]; then'."\n".
            '            tags+=(--tag "$IMAGE:latest")',
            $workflow,
        );
    }

    public function test_image_publication_does_not_deploy_to_coolify(): void
    {
        $workflow = file_get_contents(
            dirname(__DIR__, 2).'/.github/workflows/deploy.yml',
        );

        $this->assertStringNotContainsString('coolify', strtolower($workflow));
        $this->assertStringNotContainsString("\n  deploy:", $workflow);
    }
}
