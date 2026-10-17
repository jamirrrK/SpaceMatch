<?php

// Reuse only the read-only snapshot and native-client helpers. The migration
// runner in that file is explicitly bypassed. No artisan migrate/db is called.
define('GMAIL_OTP_DATABASE_FUNCTIONS_ONLY', true);
require __DIR__.'/gmail-otp-database.php';
$report = ['checked_at' => gmdate(DATE_ATOM), 'status' => 'blocked', 'migrations_executed' => false];
$credentialFile = null;
try {
    $settings = config('database.connections.mysql');
    if (config('database.default') !== 'mysql' || $settings['host'] !== '127.0.0.1' || (int) $settings['port'] !== 3306 || $settings['database'] !== 'spacematch_db' || filled($settings['url'] ?? null)) {
        throw new \RuntimeException('Unexpected target.');
    }
    $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();
    if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'spacematch_db') {
        throw new \RuntimeException('Resolved target mismatch.');
    }
    $report['target'] = ['host' => '127.0.0.1', 'port' => 3306, 'database' => 'spacematch_db'];
    $ran = \Illuminate\Support\Facades\DB::table('migrations')->pluck('migration')->all();
    $report['pending_migrations'] = array_values(array_diff(array_map(fn ($file) => pathinfo($file, PATHINFO_FILENAME), glob(database_path('migrations/*.php'))), $ran));
    $report['otp_migrations_already_applied'] = count(array_intersect($ran, ['2026_10_16_120000_add_contact_verification_to_users', '2026_10_16_120001_create_otp_verifications_table'])) === 2;
    if ($report['pending_migrations']) {
        throw new \RuntimeException('Unexpected pending migration; no migration commands will be run.');
    }
    foreach ($pdo->query('SHOW TABLE STATUS')->fetchAll(\PDO::FETCH_ASSOC) as $table) {
        if ($table['Engine'] !== 'InnoDB') {
            throw new \RuntimeException('Nontransactional table.');
        }
    }
    foreach (['ROUTINES' => 'ROUTINE_SCHEMA', 'EVENTS' => 'EVENT_SCHEMA', 'TRIGGERS' => 'TRIGGER_SCHEMA'] as $type => $column) {
        $query = $pdo->prepare("SELECT COUNT(*) FROM information_schema.{$type} WHERE {$column} = ?");
        $query->execute(['spacematch_db']);
        if ((int) $query->fetchColumn() !== 0) {
            throw new \RuntimeException('Unexpected executable database objects.');
        }
    }
    $before = otp_snapshot($pdo);
    $stamp = gmdate('Ymd_His').'_'.bin2hex(random_bytes(3));
    $directory = storage_path('app/private/backups/brevo-'.$stamp);
    if (! mkdir($directory, 0700, true)) {
        throw new \RuntimeException('Private backup directory unavailable.');
    }
    $dump = $directory.'/spacematch_db.sql';
    $credentialFile = $directory.'/client-private.cnf';
    $quote = fn ($value) => '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], (string) $value).'"';
    file_put_contents($credentialFile, "[client]\nhost=127.0.0.1\nport=3306\nuser=".$quote($settings['username'])."\npassword=".$quote($settings['password'])."\n");
    @chmod($credentialFile, 0600);
    otp_client(['C:/xampp/mysql/bin/mysqldump.exe', '--defaults-extra-file='.$credentialFile, '--single-transaction', '--skip-add-drop-table', '--skip-add-locks', '--skip-comments', '--default-character-set=utf8mb4', '--result-file='.$dump, 'spacematch_db']);
    if (! is_file($dump) || filesize($dump) < 100) {
        throw new \RuntimeException('Backup unavailable.');
    }
    $validationDatabase = 'spacematch_brevo_backupcheck_'.$stamp;
    $pdo->exec("CREATE DATABASE `{$validationDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $input = fopen($dump, 'rb');
    try {
        otp_client(['C:/xampp/mysql/bin/mysql.exe', '--defaults-extra-file='.$credentialFile, '--default-character-set=utf8mb4', '--database='.$validationDatabase], $input);
    } finally {
        fclose($input);
    }
    $copy = new \PDO('mysql:host=127.0.0.1;port=3306;dbname='.$validationDatabase.';charset=utf8mb4', $settings['username'], $settings['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
    if (otp_snapshot($copy) !== $before || otp_snapshot($pdo) !== $before) {
        throw new \RuntimeException('Source or restore snapshot mismatch.');
    }
    file_put_contents($directory.'/record-digests.json', json_encode($before, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    $report['status'] = 'backup_validated';
    $report['backup'] = ['path' => $dump, 'bytes' => filesize($dump), 'sha256' => hash_file('sha256', $dump), 'restored_and_validated' => true, 'validation_database_retained' => $validationDatabase];
    $report['original_values_unchanged'] = true;
    $report['table_counts'] = array_map(fn ($table) => $table['count'], $before);
} catch (\Throwable $error) {
    $report['failure_type'] = get_class($error);
    $report['blocked_reason'] = 'Database safety check stopped; secret-bearing diagnostics suppressed.';
} finally {
    if ($credentialFile && is_file($credentialFile)) {
        unlink($credentialFile);
    }
}
gmail_safe_json($report, __DIR__.'/../docs/brevo-database-backup.json');
exit($report['status'] === 'backup_validated' ? 0 : 1);
