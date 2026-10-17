<?php

// Records counts and irreversible digests, never account details or credentials.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$snapshotPath = storage_path('app/private/phase3-data-before.json');
$record = in_array('--record', $argv, true);
if ($record) {
    $snapshot = [];
    foreach (['users', 'user_roles', 'properties', 'spaces', 'bookings', 'payments', 'maintenance_requests', 'reviews', 'audit_logs'] as $table) {
        $columns = Illuminate\Support\Facades\Schema::getColumnListing($table);
        $rows = Illuminate\Support\Facades\DB::table($table)->select($columns)->orderBy('id')->get();
        $snapshot[$table] = ['columns' => $columns, 'count' => $rows->count(), 'digest' => hash('sha256', $rows->toJson())];
    }
    file_put_contents($snapshotPath, json_encode($snapshot, JSON_PRETTY_PRINT));
    echo json_encode(array_map(fn ($entry) => ['count' => $entry['count']], $snapshot), JSON_PRETTY_PRINT).PHP_EOL;
    exit(0);
}
$snapshot = json_decode(file_get_contents($snapshotPath), true, flags: JSON_THROW_ON_ERROR);
$success = true;
foreach ($snapshot as $table => $before) {
    $rows = Illuminate\Support\Facades\DB::table($table)->select($before['columns'])->orderBy('id')->get();
    $matches = hash_equals($before['digest'], hash('sha256', $rows->toJson()));
    $success = $success && $matches;
    echo $table.': '.$rows->count().' rows; original values '.($matches ? 'unchanged' : 'CHANGED').PHP_EOL;
}
exit($success ? 0 : 1);
