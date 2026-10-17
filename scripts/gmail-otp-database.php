<?php

require __DIR__.'/gmail-otp-support.php';
$app = gmail_bootstrap();
$report = ['checked_at' => gmdate(DATE_ATOM), 'status' => 'blocked'];
$credentialFile = null;
$stage = 'target_validation';

function otp_snapshot(\PDO $pdo): array
{
    $snapshot = [];
    foreach ($pdo->query('SHOW FULL TABLES')->fetchAll(\PDO::FETCH_NUM) as [$table, $type]) {
        if ($type !== 'BASE TABLE' || ! preg_match('/^[a-zA-Z0-9_]+$/D', $table)) {
            throw new \RuntimeException('Unsupported database object.');
        }
        $columns = array_column($pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(\PDO::FETCH_ASSOC), 'Field');
        $keys = $pdo->query("SHOW KEYS FROM `{$table}` WHERE Key_name = 'PRIMARY'")->fetchAll(\PDO::FETCH_ASSOC);
        $order = array_column($keys, 'Column_name') ?: $columns;
        $rows = $pdo->query("SELECT * FROM `{$table}` ORDER BY ".implode(', ', array_map(fn ($column) => '`'.$column.'`', $order)))->fetchAll(\PDO::FETCH_ASSOC);
        $snapshot[$table] = [
            'columns' => $columns, 'order' => $order, 'count' => count($rows),
            'digest' => hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR)),
            'schema_digest' => hash('sha256', $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(\PDO::FETCH_NUM)[1]),
        ];
    }
    ksort($snapshot);

    return $snapshot;
}

function otp_client(array $command, $input = null, $output = null): void
{
    $process = proc_open($command, [0 => $input ?? ['pipe', 'r'], 1 => $output ?? ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (! is_resource($process)) {
        throw new \RuntimeException('Database client did not start.');
    }
    foreach ($pipes as $index => $pipe) {
        // Discard diagnostics rather than risking credential/data output.
        if ($index !== 0) {
            stream_get_contents($pipe);
        }
        fclose($pipe);
    }
    if (proc_close($process) !== 0) {
        throw new \RuntimeException('Database client failed.');
    }
}

if (defined('GMAIL_OTP_DATABASE_FUNCTIONS_ONLY')) {
    return;
}

try {
    $connection = config('database.default');
    $settings = config('database.connections.'.$connection);
    if ($connection !== 'mysql' || $settings['host'] !== '127.0.0.1' || (int) $settings['port'] !== 3306 || $settings['database'] !== 'spacematch_db' || filled($settings['url'] ?? null)) {
        throw new \RuntimeException('Unexpected development database target.');
    }
    $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();
    if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'spacematch_db') {
        throw new \RuntimeException('Resolved target mismatch.');
    }
    $report['target'] = ['connection' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306, 'database' => 'spacematch_db'];
    $expected = ['2026_10_16_120000_add_contact_verification_to_users', '2026_10_16_120001_create_otp_verifications_table'];
    $ran = \Illuminate\Support\Facades\DB::table('migrations')->orderBy('id')->get()->toArray();
    $ranNames = array_map(fn ($row) => $row->migration, $ran);
    $pending = array_values(array_diff(array_map(fn ($file) => pathinfo($file, PATHINFO_FILENAME), glob(database_path('migrations/*.php'))), $ranNames));
    sort($pending);
    if ($pending !== $expected) {
        throw new \RuntimeException('Pending migrations differ from the two reviewed OTP migrations.');
    }
    $report['pending_reviewed_migrations'] = $pending;
    foreach ($pdo->query('SHOW TABLE STATUS')->fetchAll(\PDO::FETCH_ASSOC) as $table) {
        if ($table['Engine'] !== 'InnoDB') {
            throw new \RuntimeException('A table does not support transactional backup.');
        }
    }
    foreach (['ROUTINES' => 'ROUTINE_SCHEMA', 'EVENTS' => 'EVENT_SCHEMA', 'TRIGGERS' => 'TRIGGER_SCHEMA'] as $type => $column) {
        $query = $pdo->prepare("SELECT COUNT(*) FROM information_schema.{$type} WHERE {$column} = ?");
        $query->execute(['spacematch_db']);
        if ((int) $query->fetchColumn() !== 0) {
            throw new \RuntimeException('Unexpected executable database objects require a separate backup review.');
        }
    }
    $before = otp_snapshot($pdo);
    $stamp = gmdate('Ymd_His').'_'.bin2hex(random_bytes(3));
    $directory = storage_path('app/private/backups/gmail-otp-'.$stamp);
    if (! mkdir($directory, 0700, true)) {
        throw new \RuntimeException('Could not create private backup directory.');
    }
    $dump = $directory.'/spacematch_db-before-otp.sql';
    $credentialFile = $directory.'/client-private.cnf';
    $quote = fn ($value) => '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], (string) $value).'"';
    file_put_contents($credentialFile, "[client]\nhost=127.0.0.1\nport=3306\nuser=".$quote($settings['username'])."\npassword=".$quote($settings['password'])."\n");
    @chmod($credentialFile, 0600);
    otp_client([
        'C:/xampp/mysql/bin/mysqldump.exe', '--defaults-extra-file='.$credentialFile,
        '--single-transaction', '--skip-add-drop-table', '--skip-add-locks', '--skip-comments',
        '--default-character-set=utf8mb4', '--result-file='.$dump, 'spacematch_db',
    ]);
    if (! is_file($dump) || filesize($dump) < 100) {
        throw new \RuntimeException('Backup is missing or empty.');
    }
    $validationDatabase = 'spacematch_otp_backupcheck_'.$stamp;
    $pdo->exec("CREATE DATABASE `{$validationDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $input = fopen($dump, 'rb');
    try {
        otp_client(['C:/xampp/mysql/bin/mysql.exe', '--defaults-extra-file='.$credentialFile, '--default-character-set=utf8mb4', '--database='.$validationDatabase], $input);
    } finally {
        fclose($input);
    }
    $validation = new \PDO('mysql:host=127.0.0.1;port=3306;dbname='.$validationDatabase.';charset=utf8mb4', $settings['username'], $settings['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
    if (otp_snapshot($validation) !== $before || otp_snapshot($pdo) !== $before) {
        throw new \RuntimeException('Backup restore or source consistency validation failed.');
    }
    $report['backup'] = ['path' => $dump, 'bytes' => filesize($dump), 'sha256' => hash_file('sha256', $dump), 'restored_and_validated' => true, 'validation_database_retained' => $validationDatabase];
    file_put_contents($directory.'/original-record-digests.json', json_encode($before, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    $stage = 'backup_validated';
    if (! in_array('--apply-reviewed-migrations', $argv, true)) {
        $report['status'] = 'backup_validated';
    } else {
        $stage = 'applying_reviewed_migrations';
        $exit = \Illuminate\Support\Facades\Artisan::call('migrate', ['--path' => array_map(fn ($name) => 'database/migrations/'.$name.'.php', $expected), '--force' => true]);
        if ($exit !== 0) {
            throw new \RuntimeException('Migration execution failed.');
        }
        $stage = 'verifying_original_records';
        foreach ($before as $table => $snapshot) {
            $projection = implode(', ', array_map(fn ($column) => '`'.$column.'`', $snapshot['columns']));
            $order = implode(', ', array_map(fn ($column) => '`'.$column.'`', $snapshot['order']));
            $where = $table === 'migrations' ? ' WHERE id IN ('.implode(',', array_map(fn ($row) => (int) $row->id, $ran)).')' : '';
            $rows = $pdo->query("SELECT {$projection} FROM `{$table}`{$where} ORDER BY {$order}")->fetchAll(\PDO::FETCH_ASSOC);
            if (count($rows) !== $snapshot['count'] || ! hash_equals($snapshot['digest'], hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR)))) {
                throw new \RuntimeException('An original record changed.');
            }
        }
        $report['status'] = 'migrations_applied';
        $report['original_record_values_preserved'] = true;
        $report['otp_table_created_empty'] = \Illuminate\Support\Facades\DB::table('otp_verifications')->count() === 0;
        $report['applied_migrations'] = $expected;
        $report['table_counts_before'] = array_map(fn ($table) => $table['count'], $before);
    }
} catch (\Throwable $error) {
    $report['failed_stage'] = $stage;
    $report['error_class'] = get_class($error);
    $report['blocked_reason'] = 'Database safety validation or migration failed. No credentials, database rows, or exception details were displayed. Inspect the private backup state before retrying.';
} finally {
    if ($credentialFile !== null && is_file($credentialFile)) {
        unlink($credentialFile);
    }
}
gmail_safe_json($report, __DIR__.'/../docs/gmail-otp-database.json');
exit($report['status'] === 'blocked' ? 1 : 0);
