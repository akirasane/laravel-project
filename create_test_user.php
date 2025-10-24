<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

// Create a simple test user
$user = User::create([
    'name' => 'Test Admin',
    'email' => 'admin@test.com',
    'password' => bcrypt('password123')
]);

echo "User created successfully!\n";
echo "Email: admin@test.com\n";
echo "Password: password123\n";