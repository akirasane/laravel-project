<?php

require_once 'vendor/autoload.php';

use App\Services\OrderNormalizer;

// Simple test to verify OrderNormalizer functionality
$normalizer = new OrderNormalizer();

// Test Shopee order normalization
$shopeeOrder = [
    'order_sn' => 'SP123456789',
    'recipient_address' => [
        'name' => 'John Doe',
        'phone' => '+60123456789',
        'full_address' => '123 Main St',
        'city' => 'Kuala Lumpur',
        'state' => 'Selangor',
        'country' => 'Malaysia',
        'zipcode' => '50000'
    ],
    'total_amount' => 5000000, // Shopee micro-currency
    'currency' => 'MYR',
    'order_status' => 'R