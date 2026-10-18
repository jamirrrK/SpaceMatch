<?php
$app = require __DIR__.'/student-browser-bootstrap.php';
$root = getenv('SPACEMATCH_STUDENT_BROWSER_ROOT');
if (filesize($root.'/database.sqlite') !== 0) { throw new RuntimeException('Only a brand-new isolated database may be initialized.'); }
Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
$password = 'Browser-Student-2026!';
$owner = App\Models\User::factory()->create(['name' => 'Browser Housing Owner', 'email' => 'owner@student-browser.example.test', 'role' => 'owner', 'password' => $password]);
$admin = App\Models\User::factory()->create(['name' => 'Browser Administrator', 'email' => 'admin@student-browser.example.test', 'role' => 'admin', 'password' => $password]);
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
file_put_contents($root.'/fixtures.json', json_encode(['property_id' => $property->id, 'space_ids' => array_map(fn ($s) => $s->id, $spaces), 'owner_email' => $owner->email, 'admin_email' => $admin->email, 'password' => $password], JSON_THROW_ON_ERROR));
echo "Isolated browser fixtures initialized. No existing database modified.\n";
