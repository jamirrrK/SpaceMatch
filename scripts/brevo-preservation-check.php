<?php

define('GMAIL_OTP_DATABASE_FUNCTIONS_ONLY', true);
require __DIR__.'/gmail-otp-database.php';
$report = ['original_database_unchanged' => false, 'credentials_displayed' => false];
try {
    $backup = json_decode(file_get_contents(__DIR__.'/../docs/brevo-database-backup.json'), true, flags: JSON_THROW_ON_ERROR);
    if (config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'spacematch_db') {
        throw new \RuntimeException('Target mismatch.');
    }
    $before = json_decode(file_get_contents(dirname($backup['backup']['path']).'/record-digests.json'), true, flags: JSON_THROW_ON_ERROR);
    $report['original_database_unchanged'] = otp_snapshot(\Illuminate\Support\Facades\DB::connection()->getPdo()) === $before;
    $report['tables_checked'] = count($before);
} catch (\Throwable) {
    $report['blocked_reason'] = 'Preservation check failed; diagnostics suppressed.';
}
gmail_safe_json($report, __DIR__.'/../docs/brevo-data-preservation.json');
exit($report['original_database_unchanged'] ? 0 : 1);
