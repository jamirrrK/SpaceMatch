<?php
// Local browser harness only. This file is outside public/ and is never included by the app.
$workspace = dirname(__DIR__);
$root = getenv('SPACEMATCH_STUDENT_BROWSER_ROOT');
$parent = realpath($workspace.'/storage/app/private');
if (!in_array(PHP_SAPI, ['cli', 'cli-server'], true) || !$root || !is_dir($root)
    || realpath(dirname($root)) !== $parent || !str_starts_with(basename($root), 'student-workflow-browser-')
    || !is_file($root.'/.isolated-student-browser')) {
    throw new RuntimeException('Refusing browser harness outside its dedicated local fixture directory.');
}
$database = $root.'/database.sqlite';
$environment = [
    'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_URL' => 'http://127.0.0.1:8027',
    'APP_KEY' => getenv('SPACEMATCH_STUDENT_BROWSER_KEY'),
    'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => '',
    'APP_CONFIG_CACHE' => $root.'/bootstrap-cache/config.php', 'APP_ROUTES_CACHE' => $root.'/bootstrap-cache/routes.php',
    'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'file', 'SESSION_COOKIE' => 'student_browser_session',
    'SESSION_SECURE_COOKIE' => 'false', 'SESSION_DOMAIN' => '', 'QUEUE_CONNECTION' => 'sync',
    'MAIL_MAILER' => 'array', 'OTP_EMAIL_DRIVER' => 'mail', 'OTP_EMAIL_ENABLED' => 'false',
    'OTP_TESTING_EMAIL_DELIVERY' => 'true', 'SMS_OTP_ENABLED' => 'false', 'BCRYPT_ROUNDS' => '4',
];
if (!$environment['APP_KEY'] || !str_starts_with($environment['APP_KEY'], 'base64:')) {
    throw new RuntimeException('A separate browser test application key is required.');
}
foreach ($environment as $key => $value) { putenv($key.'='.$value); $_ENV[$key] = $value; $_SERVER[$key] = $value; }
foreach (['framework/cache/data', 'framework/sessions', 'framework/views', 'logs', 'app/private'] as $directory) {
    if (!is_dir($root.'/storage/'.$directory)) { mkdir($root.'/storage/'.$directory, 0700, true); }
}
if (!is_file($database)) { touch($database); }
require_once $workspace.'/vendor/autoload.php';
$app = require $workspace.'/bootstrap/app.php';
$app->useStoragePath($root.'/storage');
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $database
    || !app()->environment('testing') || config('mail.default') !== 'array') {
    throw new RuntimeException('Unsafe browser configuration.');
}
// Keep fixture sessions valid when advancing the isolated lifecycle clock by one month.
config(['session.lifetime' => 60 * 24 * 365]);
Illuminate\Support\Carbon::setTestNow(trim(file_get_contents($root.'/clock.txt')));
Illuminate\Support\Facades\Http::preventStrayRequests();
Illuminate\Support\Facades\Event::listen(Illuminate\Mail\Events\MessageSending::class, function ($event) use ($root): void {
    if (isset($event->data['code'])) {
        file_put_contents($root.'/otp.json', json_encode(['code' => $event->data['code']], JSON_THROW_ON_ERROR));
    }
});
return $app;
