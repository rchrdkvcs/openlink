<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class WorkspaceSessionConcurrencyTest extends TestCase
{
    public function test_a_slow_request_cannot_revert_the_workspace_after_switching(): void
    {
        $directory = sys_get_temp_dir().'/openlink-session-'.Str::random(16);
        File::makeDirectory($directory);
        $id = Str::random(40);
        $worker = fn (string $operation) => new Process([
            PHP_BINARY, base_path('tests/Fixtures/workspace-session-request.php'), $directory, $id, $operation,
        ], base_path());
        $slow = $worker('slow');
        $switch = $worker('second');

        try {
            $worker('first')->mustRun();
            $slow->start();
            $deadline = microtime(true) + 5;
            while (! file_exists($directory.'/started') && microtime(true) < $deadline) {
                usleep(10_000);
            }
            $this->assertFileExists($directory.'/started');
            $switch->start();
            $switch->wait();
            $slow->wait();
            $this->assertTrue($switch->isSuccessful(), $switch->getErrorOutput());
            $this->assertTrue($slow->isSuccessful(), $slow->getErrorOutput());
            $this->assertSame('second', $switch->getOutput());
            $nextPage = $worker('read');
            $nextPage->mustRun();
            $this->assertSame('second', $nextPage->getOutput(), 'Navigation must retain the selected workspace after an older request completes.');
        } finally {
            $slow->stop();
            $switch->stop();
            File::deleteDirectory($directory);
        }
    }
}
