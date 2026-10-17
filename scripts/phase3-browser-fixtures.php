<?php

// All browser accounts and uploads live in a dedicated, checked SQLite database.
$workspace = realpath(__DIR__.'/..');
$privateDirectory = $workspace.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'private';
if (! is_dir($privateDirectory) && ! mkdir($privateDirectory, 0775, true) && ! is_dir($privateDirectory)) {
    throw new RuntimeException('Unable to create the isolated browser fixture directory.');
}
$databasePath = $privateDirectory.DIRECTORY_SEPARATOR.'phase3-browser.sqlite';
$imageRoot = $privateDirectory.DIRECTORY_SEPARATOR.'phase3-browser-images';
$environment = [
    'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_URL' => 'http://127.0.0.1:8013',
    'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $databasePath, 'DB_URL' => '',
    'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'file', 'MAIL_MAILER' => 'array',
    'QUEUE_CONNECTION' => 'sync', 'PROPERTY_IMAGE_ROOT' => $imageRoot,
];
foreach ($environment as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

require $workspace.'/vendor/autoload.php';
$app = require $workspace.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$resolvedDirectory = realpath(dirname($databasePath));
if ($resolvedDirectory !== realpath($privateDirectory)
    || basename($databasePath) !== 'phase3-browser.sqlite'
    || config('database.default') !== 'sqlite'
    || config('database.connections.sqlite.database') !== $databasePath) {
    throw new RuntimeException('Refusing to prepare fixtures outside the dedicated browser SQLite database.');
}
if (! file_exists($databasePath)) {
    touch($databasePath);
}
clearstatcache(true, $databasePath);
if (filesize($databasePath) === 0) {
    if (in_array('--no-migrate', $argv, true)) {
        throw new RuntimeException('The no-migrate option requires an existing isolated browser database.');
    }
    Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
} elseif (! Illuminate\Support\Facades\Schema::hasColumn('properties', 'listing_status')) {
    throw new RuntimeException('The isolated browser database predates Phase 3. Use a new dedicated fixture file after reviewing it.');
}
if (! in_array('--no-migrate', $argv, true)) {
    Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
} elseif (! Illuminate\Support\Facades\Schema::hasTable('otp_verifications')
    || ! Illuminate\Support\Facades\Schema::hasColumn('users', 'verification_required_at')) {
    throw new RuntimeException('The existing isolated database is not ready for OTP browser tests. No migrations were run.');
}

$password = 'SpaceMatch-Browser-2026!';
$accounts = [];
foreach (['owner', 'admin', 'renter', 'other_owner'] as $label) {
    $role = $label === 'other_owner' ? 'owner' : $label;
    $accounts[$label] = App\Models\User::updateOrCreate(
        ['email' => 'phase3-'.$label.'@browser.example.test'],
        ['name' => 'Browser QA '.ucwords(str_replace('_', ' ', $label)), 'role' => $role, 'password' => $password],
    );
}

$otpAccount = App\Models\User::updateOrCreate(
    ['email' => 'phase3-otp-renter@browser.example.test'],
    ['name' => 'Browser QA OTP Renter', 'role' => 'renter', 'password' => 'SpaceMatch-Browser-2026!'],
);
$otpAccount->forceFill([
    'email_verified_at' => null,
    'phone_number' => null,
    'phone_verified_at' => null,
    'verification_method' => 'email',
    'verification_required_at' => now(),
])->save();
$otpAccount->otpVerifications()->where('purpose', 'registration')->whereNull('consumed_at')->update(['consumed_at' => now()]);
$browserOtp = '613904';
$otpAccount->otpVerifications()->create([
    'verification_type' => 'email',
    'purpose' => 'registration',
    'destination_hash' => hash_hmac('sha256', 'email|'.strtolower($otpAccount->email), (string) config('app.key')),
    'destination_encrypted' => $otpAccount->email,
    'code_hash' => Illuminate\Support\Facades\Hash::make($browserOtp),
    'attempts' => 0,
    'sent_at' => now(),
    'expires_at' => now()->addMinutes(5),
]);

$properties = [];
foreach ([
    ['published', 'Browser QA Published Cebu', 'Cebu City', 'published', 'active', 6500],
    ['draft', 'Browser QA Draft Cebu', 'Cebu City', 'draft', 'active', 4500],
    ['archived', 'Browser QA Archived Cebu', 'Cebu City', 'published', 'archived', 5500],
    ['other_owner', 'Browser QA Other Owner Manila', 'Manila', 'published', 'active', 12000],
] as [$label, $name, $city, $listing, $status, $price]) {
    $owner = $accounts[$label === 'other_owner' ? 'other_owner' : 'owner'];
    $property = App\Models\Property::firstOrCreate(
        ['owner_id' => $owner->id, 'property_name' => $name],
        ['description' => 'Isolated browser QA fixture for Phase 3.', 'property_type' => 'apartment', 'address' => '24 Browser Test Street', 'barangay' => 'Lahug', 'city' => $city, 'province' => $city === 'Manila' ? 'Metro Manila' : 'Cebu', 'postal_code' => '6000', 'listing_status' => $listing, 'status' => $status],
    );
    $space = $property->spaces()->firstOrCreate(
        ['space_name' => 'Browser QA '.$label.' space'],
        ['space_type' => 'studio_unit', 'description' => 'Fixture space for search and filtering.', 'price' => $price, 'capacity' => 2, 'floor_area' => 24, 'floor_number' => 2, 'availability_status' => 'available', 'listing_status' => 'published'],
    );
    $space->amenities()->sync(App\Models\Amenity::whereIn('name', ['Wi-Fi', 'Security'])->pluck('id'));
    $properties[$label] = ['id' => $property->id, 'space_id' => $space->id];
}

$manifest = [
    'url' => $environment['APP_URL'], 'database' => $databasePath, 'image_root' => $imageRoot,
    'accounts' => array_map(fn ($user) => ['id' => $user->id, 'email' => $user->email], $accounts),
    'otp_account' => ['id' => $otpAccount->id, 'email' => $otpAccount->email],
    'properties' => $properties,
    'wifi_id' => App\Models\Amenity::where('name', 'Wi-Fi')->value('id'),
];
file_put_contents($privateDirectory.DIRECTORY_SEPARATOR.'phase3-browser-fixtures.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
