<?php
$app = require __DIR__.'/student-browser-bootstrap.php';
$root = getenv('SPACEMATCH_STUDENT_BROWSER_ROOT');
if (filesize($root.'/database.sqlite') !== 0) { throw new RuntimeException('Only a brand-new isolated database may be initialized.'); }
Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
$password = 'Browser-Student-2026!';
$owner = App\Models\User::factory()->create(['name' => 'Browser Housing Owner', 'email' => 'owner@student-browser.example.test', 'role' => 'owner', 'password' => $password]);
$admin = App\Models\User::factory()->create(['name' => 'Browser Administrator', 'email' => 'admin@student-browser.example.test', 'role' => 'admin', 'password' => $password]);
$masterAdmin = App\Models\User::factory()->create(['name' => 'Browser Master Administrator', 'email' => 'master-admin@student-browser.example.test', 'role' => 'master_admin', 'password' => $password]);
$targetAdmin = App\Models\User::factory()->create(['name' => 'Browser Access Review', 'email' => 'target-admin@student-browser.example.test', 'role' => 'admin', 'password' => $password]);
$other = App\Models\User::factory()->create(['role' => 'renter', 'email' => 'other@student-browser.example.test', 'password' => $password]);
$property = App\Models\Property::factory()->published()->create(['owner_id' => $owner->id, 'property_name' => 'Browser Student Residences', 'city' => 'Cebu City', 'province' => 'Cebu']);
$spaces = [];
foreach (['Study Room Alpha', 'Study Room Beta'] as $name) {
    $spaces[] = App\Models\Space::factory()->published()->create(['property_id' => $property->id, 'space_name' => $name,
        'space_type' => 'private_room', 'price' => 5000, 'capacity' => 2, 'student_friendly' => true,
        'nearby_school' => 'Browser Test University Main', 'campus_distance_km' => 1.5, 'furnishing' => 'furnished',
        'deposit_amount' => 5000, 'advance_months' => 1, 'house_rules' => 'Quiet hours after 10 PM.',
        'utilities_notes' => 'Water included. Electricity billed at metered cost.']);
}
$imagePath = 'property-images/'.$property->id.'/featured.png';
$imageRoot = storage_path('app/private/property-media/'.dirname($imagePath));
if (!is_dir($imageRoot)) { mkdir($imageRoot, 0700, true); }
file_put_contents(storage_path('app/private/property-media/'.$imagePath), base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/3xkAAAAASUVORK5CYII=', true));
App\Models\PropertyImage::query()->create([
    'property_id' => $property->id,
    'image_path' => $imagePath,
    'is_primary' => true,
    'display_order' => 0,
]);
file_put_contents($root.'/fixtures.json', json_encode([
    'property_id' => $property->id,
    'space_ids' => array_map(fn ($s) => $s->id, $spaces),
    'owner_email' => $owner->email,
    'admin_email' => $admin->email,
    'master_admin_email' => $masterAdmin->email,
    'target_admin_email' => $targetAdmin->email,
    'target_admin_id' => $targetAdmin->id,
    'password' => $password,
], JSON_THROW_ON_ERROR));
echo "Isolated browser fixtures initialized. No existing database modified.\n";
