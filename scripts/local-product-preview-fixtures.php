<?php

$app = require __DIR__.'/student-browser-bootstrap.php';
if (App\Models\User::where('email', 'student@student-browser.example.test')->exists()) {
    throw new RuntimeException('Preview fixtures already exist. Start a new isolated preview.');
}
App\Models\User::factory()->create([
    'name' => 'Demo Student Renter', 'role' => 'renter', 'is_student' => true,
    'email' => 'student@student-browser.example.test', 'password' => 'Browser-Student-2026!',
]);
echo "Synthetic student account created in the isolated demonstration database.\n";
