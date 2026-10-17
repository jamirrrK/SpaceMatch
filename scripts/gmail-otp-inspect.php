<?php
require __DIR__.'/gmail-otp-support.php';
gmail_bootstrap();
try {
    $ran = \Illuminate\Support\Facades\DB::table('migrations')->pluck('migration')->all();
    $pending = array_values(array_diff(array_map(fn ($file) => pathinfo($file, PATHINFO_FILENAME), glob(database_path('migrations/*.php'))), $ran));
    gmail_safe_json([
        'pending_migrations' => $pending,
        'users_phone_column_exists' => \Illuminate\Support\Facades\Schema::hasColumn('users', 'phone_number'),
        'otp_table_exists' => \Illuminate\Support\Facades\Schema::hasTable('otp_verifications'),
        'original_backup_report' => json_decode(file_get_contents(__DIR__.'/../docs/gmail-otp-database.json'), true)['status'],
        'user_id_definition' => \Illuminate\Support\Facades\DB::select("SHOW COLUMNS FROM users WHERE Field = 'id'"),
        'database_version' => \Illuminate\Support\Facades\DB::selectOne('SELECT VERSION() AS version')->version,
    ]);
} catch (\Throwable $error) {
    gmail_safe_json(['status' => 'blocked', 'error_class' => get_class($error)]);
}
