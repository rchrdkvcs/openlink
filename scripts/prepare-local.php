<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$environmentPath = $root.'/.env';
$examplePath = $root.'/.env.example';

if (! is_file($environmentPath) && ! copy($examplePath, $environmentPath)) {
    fwrite(STDERR, "Unable to create .env from .env.example.\n");
    exit(1);
}

$environment = file_get_contents($environmentPath);

if ($environment === false || ! preg_match('/^APP_KEY=([^\r\n]*)/m', $environment, $matches)) {
    fwrite(STDERR, "Unable to read APP_KEY from .env.\n");
    exit(1);
}

if (trim($matches[1]) === '') {
    $key = 'base64:'.base64_encode(random_bytes(32));
    $updated = preg_replace('/^APP_KEY=[^\r\n]*/m', 'APP_KEY='.$key, $environment, 1, $replacements);

    if ($updated === null || $replacements !== 1 || file_put_contents($environmentPath, $updated, LOCK_EX) === false) {
        fwrite(STDERR, "Unable to write APP_KEY to .env.\n");
        exit(1);
    }

    fwrite(STDOUT, "Generated APP_KEY in .env.\n");
} else {
    fwrite(STDOUT, "Keeping the existing APP_KEY in .env.\n");
}

foreach ([
    'storage/app/private',
    'storage/app/public',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/testing',
    'storage/framework/views',
] as $directory) {
    $path = $root.'/'.str_replace('/', DIRECTORY_SEPARATOR, $directory);

    if (! is_dir($path) && ! mkdir($path, 0775, true) && ! is_dir($path)) {
        fwrite(STDERR, "Unable to create {$directory}.\n");
        exit(1);
    }
}

fwrite(STDOUT, "Local Laravel storage directories are ready.\n");
