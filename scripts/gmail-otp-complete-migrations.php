<?php

// Resume only the reviewed additive migration after validating the original
// restored backup and every original value, including a partially applied DDL.
define('GMAIL_OTP_DATABASE_FUNCTIONS_ONLY', true);
require __DIR__.'/gmail-otp-database.php';
$report = json_decode(file_get_contents(__DIR__.'/../docs/gmail-otp-database.json'), true, flags: JSON_THROW_ON_ERROR);
$stage = 'validate_original_backup';
try {
    $backup = $report['backup'];
    if (! $backup['restored_and_validated'] || ! hash_equals($backup['sha256'], hash_file('sha256', $backup['path']))) {
        throw new \RuntimeException('Original backup validation failed.');
    }
    $settings = config('database.connections.mysql');
    if (config('database.default') !== 'mysql' || $settings['database'] !== 'spacematch_db' || $settings['host'] !== '127.0.0.1' || (int) $settings['port'] !== 3306 || filled($settings['url'] ?? null)) {
        throw new \RuntimeException('Unexpected target.');
    }
    $validationDatabase = $backup['validation_database_retained'];
    if (! preg_match('/^spacematch_otp_backupcheck_[a-zA-Z0-9_]+$/D', $validationDatabase)) {
        throw new \RuntimeException('Unexpected validation database.');
    }
    $validation = new \PDO('mysql:host=127.0.0.1;port=3306;dbname='.$validationDatabase.';charset=utf8mb4', $settings['username'], $settings['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
    $before = json_decode(file_get_contents(dirname($backup['path']).'/original-record-digests.json'), true, flags: JSON_THROW_ON_ERROR);
    if (otp_snapshot($validation) !== $before) {
        throw new \RuntimeException('Restored original backup changed.');
    }
    $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();
    if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'spacematch_db') {
        throw new \RuntimeException('Resolved target mismatch.');
    }
    $originalMigrationIds = $validation->query('SELECT id FROM migrations ORDER BY id')->fetchAll(\PDO::FETCH_COLUMN);
    $verifyOriginal = function () use ($before, $pdo, $originalMigrationIds): void {
        foreach ($before as $table => $snapshot) {
            $projection = implode(', ', array_map(fn ($column) => '`'.$column.'`', $snapshot['columns']));
            $order = implode(', ', array_map(fn ($column) => '`'.$column.'`', $snapshot['order']));
            $where = $table === 'migrations' ? ' WHERE id IN ('.implode(',', array_map('intval', $originalMigrationIds)).')' : '';
            $rows = $pdo->query("SELECT {$projection} FROM `{$table}`{$where} ORDER BY {$order}")->fetchAll(\PDO::FETCH_ASSOC);
            if (count($rows) !== $snapshot['count'] || ! hash_equals($snapshot['digest'], hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR)))) {
                throw new \RuntimeException('Original values do not match the validated backup.');
            }
        }
    };
    $verifyOriginal();
    $expected = ['2026_10_16_120000_add_contact_verification_to_users', '2026_10_16_120001_create_otp_verifications_table'];
    $ran = \Illuminate\Support\Facades\DB::table('migrations')->pluck('migration')->all();
    $pending = array_values(array_diff(array_map(fn ($file) => pathinfo($file, PATHINFO_FILENAME), glob(database_path('migrations/*.php'))), $ran));
    if (array_diff($pending, $expected)) {
        throw new \RuntimeException('Unexpected pending migrations.');
    }
    $stage = 'apply_remaining_reviewed_migrations';
    if ($pending && \Illuminate\Support\Facades\Artisan::call('migrate', ['--path' => array_map(fn ($name) => 'database/migrations/'.$name.'.php', $pending), '--force' => true]) !== 0) {
        throw new \RuntimeException('Migration failed.');
    }
    $stage = 'verify_original_records_after_migration';
    $verifyOriginal();
    $report['status'] = 'migrations_applied';
    $report['completed_at'] = gmdate(DATE_ATOM);
    $report['applied_migrations'] = $expected;
    $report['original_record_values_preserved'] = true;
    $report['otp_table_created_empty'] = \Illuminate\Support\Facades\DB::table('otp_verifications')->count() === 0;
    $report['table_counts_before'] = array_map(fn ($table) => $table['count'], $before);
    $report['compatibility_fix'] = 'Explicit short composite index name and valid timestamp defaults for MariaDB 10.4. Issue() still supplies the five-minute expiry; omitted expiry defaults to immediate expiration.';
    unset($report['blocked_reason'], $report['failed_stage'], $report['error_class'], $report['sqlstate'], $report['database_error_code']);
} catch (\Throwable $error) {
    $report['status'] = 'blocked';
    $report['failed_stage'] = $stage;
    $report['error_class'] = get_class($error);
    if ($error instanceof \Illuminate\Database\QueryException) {
        $report['sqlstate'] = $error->errorInfo[0] ?? null;
        $report['database_error_code'] = $error->errorInfo[1] ?? null;
    }
    $report['blocked_reason'] = 'Migration completion stopped safely. No secret-bearing exception was displayed.';
}
gmail_safe_json($report, __DIR__.'/../docs/gmail-otp-database.json');
exit($report['status'] === 'migrations_applied' ? 0 : 1);
