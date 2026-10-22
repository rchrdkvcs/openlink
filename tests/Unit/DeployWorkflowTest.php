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

    public function test_release_channels_publish_the_expected_tags(): void
    {
        $cases = [
            ['v1.6.0-beta.1', 'true', 'current', 'latest', ['publish' => 'true', 'beta' => 'true'], ['v1.6.0-beta.1', 'beta']],
            ['1.6.0-beta', 'true', 'current', 'latest', ['publish' => 'true', 'beta' => 'true'], ['1.6.0-beta', 'beta']],
            ['v1.6.0-alpha.1', 'true', 'current', 'latest', ['publish' => 'true'], ['v1.6.0-alpha.1']],
            ['v1.6.0-rc.1', 'true', 'current', 'latest', ['publish' => 'true'], ['v1.6.0-rc.1']],
            ['v1.6.0-betamax.1', 'true', 'current', 'latest', ['publish' => 'true'], ['v1.6.0-betamax.1']],
            ['v1.6.0', 'false', 'latest', 'latest', ['publish' => 'true', 'latest' => 'true'], ['v1.6.0', 'latest']],
            ['v1.5.4', 'false', 'older', 'latest', [], []],
            ['v1.6.0-beta.1', 'false', 'latest', 'latest', ['publish' => 'true', 'latest' => 'true'], ['v1.6.0-beta.1', 'latest']],
        ];

        foreach ($cases as [$tag, $prerelease, $releaseId, $latestId, $expectedOutputs, $expectedTags]) {
            [$outputs] = $this->runWorkflowStep('Select release publication', [
                'TAG' => $tag,
                'PRERELEASE' => $prerelease,
                'RELEASE_ID' => $releaseId,
                'LATEST_RELEASE_ID' => $latestId,
                'GITHUB_REPOSITORY' => 'example/openlink',
            ]);

            $this->assertSame($expectedOutputs, $outputs, $tag);

            if (($outputs['publish'] ?? '') !== 'true') {
                continue;
            }

            [, $command] = $this->runWorkflowStep('Publish release manifest', [
                'TAG' => $tag,
                'IMAGE' => 'ghcr.io/example/openlink',
                'PUBLISH_LATEST' => $outputs['latest'] ?? '',
                'PUBLISH_BETA' => $outputs['beta'] ?? '',
            ]);

            preg_match_all('/--tag ghcr\.io\/example\/openlink:([^ ]+)/', $command, $matches);
            $this->assertSame($expectedTags, $matches[1], $tag);
        }
    }

    public function test_beta_output_is_forwarded_to_manifest_publication(): void
    {
        $workflow = file_get_contents(dirname(__DIR__, 2).'/.github/workflows/deploy.yml');

        $this->assertStringContainsString('beta: ${{ steps.release.outputs.beta }}', $workflow);
        $this->assertStringContainsString('PUBLISH_BETA: ${{ needs.check-release.outputs.beta }}', $workflow);
        $this->assertStringContainsString('TAG: ${{ github.event.release.tag_name }}', $workflow);
    }

    private function runWorkflowStep(string $name, array $environment): array
    {
        $workflow = file_get_contents(dirname(__DIR__, 2).'/.github/workflows/deploy.yml');
        $matched = preg_match(
            '/      - name: '.preg_quote($name, '/').'\n.*?        run: \|\n((?:          [^\n]*\n|\n)+)/s',
            $workflow,
            $matches,
        );
        $this->assertSame(1, $matched);

        $outputPath = tempnam(sys_get_temp_dir(), 'release-output-');
        $workingDirectory = sys_get_temp_dir().'/release-digests-'.bin2hex(random_bytes(8));
        mkdir($workingDirectory);
        touch($workingDirectory.'/example');

        try {
            $script = 'gh() { printf "%s\n" "$LATEST_RELEASE_ID"; }'."\n".
                'docker() { printf "%s " "$@"; }'."\n".
                preg_replace('/^ {10}/m', '', $matches[1]);
            $process = proc_open(
                ['bash', '-e', '-c', $script],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                $workingDirectory,
                [...getenv(), ...$environment, 'GITHUB_OUTPUT' => $outputPath],
            );
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $this->assertSame(0, proc_close($process), $stderr);

            $outputs = [];
            foreach (file($outputPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                [$key, $value] = explode('=', $line, 2);
                $outputs[$key] = $value;
            }

            return [$outputs, $stdout];
        } finally {
            unlink($outputPath);
            unlink($workingDirectory.'/example');
            rmdir($workingDirectory);
        }
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
