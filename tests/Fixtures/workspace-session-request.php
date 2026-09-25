<?php

// Separate workers reproduce requests sharing a session without sharing memory.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Session\Middleware\StartSession;
use Symfony\Component\HttpFoundation\Response;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
[$script, $directory, $id, $operation] = $argv;
$app['config']->set([
    'session.driver' => 'file',
    'session.files' => $directory,
    'session.lottery' => [0, 100],
    'session.block_store' => 'file',
    'cache.default' => 'file',
    'cache.stores.file.path' => $directory.'/cache',
    'cache.stores.file.lock_path' => $directory.'/cache',
]);
$request = Request::create('http://localhost/dashboard');
$request->cookies->set($app['config']->get('session.cookie'), $id);
$request->setRouteResolver(fn () => new Route('GET', 'dashboard', fn () => null));
if ($operation === 'second') {
    file_put_contents($directory.'/switch-started', 'ready');
}
$response = $app->make(StartSession::class)->handle($request, function (Request $request) use ($operation, $directory) {
    if ($operation === 'slow') {
        file_put_contents($directory.'/started', 'ready');
        // Wait for the other worker to boot before holding this request open.
        $deadline = microtime(true) + 5;
        while (! file_exists($directory.'/switch-started') && microtime(true) < $deadline) {
            usleep(10_000);
        }
        if (! file_exists($directory.'/switch-started')) {
            throw new RuntimeException('The switch request did not start.');
        }
        usleep(800_000);
    }
    if (in_array($operation, ['first', 'second'], true)) {
        $request->session()->put('workspace_id', $operation);
    }

    return new Response((string) $request->session()->get('workspace_id'));
});
echo $response->getContent();
