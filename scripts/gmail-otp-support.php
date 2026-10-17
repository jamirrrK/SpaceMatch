<?php

function gmail_bootstrap(): \Illuminate\Foundation\Application
{
    require_once __DIR__.'/../vendor/autoload.php';
    try {
        // Dotenv exceptions can contain malformed values. Never print them.
        \Dotenv\Dotenv::parse(file_get_contents(__DIR__.'/../.env'));
        $app = require __DIR__.'/../bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    } catch (\Throwable) {
        echo json_encode(['status' => 'blocked', 'reason' => 'Environment could not be loaded. Check local .env syntax privately.']).PHP_EOL;
        exit(78);
    }
}

function gmail_safe_json(array $report, ?string $path = null): void
{
    $json = json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
    if ($path !== null) {
        file_put_contents($path, $json);
    }
    echo $json;
}
