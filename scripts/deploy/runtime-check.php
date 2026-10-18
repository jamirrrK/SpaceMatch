<?php
// Read-only CLI probe. Never prints .env, connection credentials, or exception details.
try {
    if ($argc !== 2 || !is_dir($argv[1])) {
        throw new RuntimeException('Invalid application root');
    }
    chdir($argv[1]);
    $loader = require $argv[1].'/vendor/autoload.php';
    $app = require $argv[1].'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $extensions = [];
    foreach (get_loaded_extensions() as $name) {
        $extensions[strtolower($name)] = phpversion($name) ?: 'builtin';
    }
    ksort($extensions);
    $migrations = Illuminate\Support\Facades\DB::table('migrations')->orderBy('migration')->pluck('migration')->all();
    $classmap = [];
    foreach ($loader->getClassMap() as $class => $path) {
        if (str_starts_with($class, 'App\\')) {
            $relative = str_replace('\\', '/', $path);
            $prefix = str_replace('\\', '/', realpath($argv[1])).'/';
            if (!str_starts_with($relative, $prefix)) {
                throw new RuntimeException('Unexpected application classmap path');
            }
            $classmap[] = substr($relative, strlen($prefix));
        }
    }
    echo json_encode([
        'php_version_id' => PHP_VERSION_ID,
        'public_path' => public_path(),
        'storage_path' => storage_path(),
        'extensions' => $extensions,
        'maintenance_driver' => config('app.maintenance.driver'),
        'classmap_authoritative' => $loader->isClassMapAuthoritative(),
        'database' => Illuminate\Support\Facades\DB::connection()->getDatabaseName(),
        'migrations' => $migrations,
        'app_classmap_paths' => array_values(array_unique($classmap)),
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    fwrite(STDERR, "Read-only runtime/dependency probe failed. Inspect privately on the server.\n");
    exit(1);
}
