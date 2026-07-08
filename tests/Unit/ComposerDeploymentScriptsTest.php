<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ComposerDeploymentScriptsTest extends TestCase
{
    public function test_post_autoload_dump_clears_package_manifests_before_discovery(): void
    {
        $composer = $this->composerJson();

        $scripts = $composer['scripts']['post-autoload-dump'];
        $clearIndex = $this->scriptIndexContaining($scripts, 'bootstrap/cache');
        $discoverIndex = $this->scriptIndexContaining($scripts, 'package:discover');

        $this->assertIsInt($clearIndex);
        $this->assertIsInt($discoverIndex);
        $this->assertLessThan($discoverIndex, $clearIndex);
        $this->assertStringContainsString('packages.php', $scripts[$clearIndex]);
        $this->assertStringContainsString('services.php', $scripts[$clearIndex]);
    }

    public function test_dev_only_laravel_providers_are_not_auto_discovered(): void
    {
        $composer = $this->composerJson();

        $this->assertEqualsCanonicalizing([
            'laravel/breeze',
            'laravel/pail',
            'laravel/pao',
            'nunomaduro/collision',
        ], $composer['extra']['laravel']['dont-discover']);
    }

    /**
     * @param  array<int, string>  $scripts
     */
    private function scriptIndexContaining(array $scripts, string $needle): ?int
    {
        foreach ($scripts as $index => $script) {
            if (str_contains($script, $needle)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function composerJson(): array
    {
        return json_decode(
            file_get_contents(dirname(__DIR__, 2).'/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }
}
