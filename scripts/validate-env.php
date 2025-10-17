<?php

/**
 * Environment Configuration Validator
 * Validates required environment variables for the Order Management System
 */

$requiredVars = [
    'APP_NAME' => 'Application name',
    'APP_ENV' => 'Application environment (local, production)',
    'APP_KEY' => 'Application encryption key',
    'APP_URL' => 'Application URL',
    
    // Database
    'DB_CONNECTION' => 'Database connection type',
    'DB_HOST' => 'Database host',
    'DB_PORT' => 'Database port',
    'DB_DATABASE' => 'Database name',
    'DB_USERNAME' => 'Database username',
    'DB_PASSWORD' => 'Database password',
    
    // Redis
    'REDIS_HOST' => 'Redis host',
    'REDIS_PORT' => 'Redis port',
    
    // Queue
    'QUEUE_CONNECTION' => 'Queue connection type',
    
    // Broadcasting
    'BROADCAST_CONNECTION' => 'Broadcast connection type',
    'PUSHER_APP_ID' => 'Pusher application ID',
    'PUSHER_APP_KEY' => 'Pusher application key',
    'PUSHER_APP_SECRET' => 'Pusher application secret',
];

$environmentSpecificVars = [
    'production' => [
        'MAIL_HOST' => 'Mail server host',
        'MAIL_USERNAME' => 'Mail server username',
        'MAIL_PASSWORD' => 'Mail server password',
        'SENTRY_LARAVEL_DSN' => 'Sentry DSN for error tracking',
    ],
    'local' => [
        // Development specific variables can be added here
    ]
];

echo "🔍 Validating environment configuration...\n";

$errors = [];
$warnings = [];

// Check required variables
foreach ($requiredVars as $var => $description) {
    $value = getenv($var);
    if (empty($value)) {
        $errors[] = "❌ Missing required variable: {$var} ({$description})";
    } else {
        echo "✅ {$var}: OK\n";
    }
}

// Check environment-specific variables
$appEnv = getenv('APP_ENV') ?: 'local';
if (isset($environmentSpecificVars[$appEnv])) {
    foreach ($environmentSpecificVars[$appEnv] as $var => $description) {
        $value = getenv($var);
        if (empty($value)) {
            $warnings[] = "⚠️  Missing {$appEnv} variable: {$var} ({$description})";
        } else {
            echo "✅ {$var}: OK\n";
        }
    }
}

// Validate specific configurations
if (getenv('APP_ENV') === 'production') {
    if (getenv('APP_DEBUG') === 'true') {
        $errors[] = "❌ APP_DEBUG should be false in production";
    }
    
    if (empty(getenv('APP_KEY')) || getenv('APP_KEY') === 'base64:') {
        $errors[] = "❌ APP_KEY must be properly generated in production";
    }
}

// Database connection test
echo "\n🔍 Testing database connection...\n";
try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s',
        getenv('DB_HOST'),
        getenv('DB_PORT'),
        getenv('DB_DATABASE')
    );
    
    $pdo = new PDO($dsn, getenv('DB_USERNAME'), getenv('DB_PASSWORD'));
    echo "✅ Database connection: OK\n";
} catch (PDOException $e) {
    $errors[] = "❌ Database connection failed: " . $e->getMessage();
}

// Redis connection test
echo "\n🔍 Testing Redis connection...\n";
try {
    $redis = new Redis();
    $redis->connect(getenv('REDIS_HOST'), getenv('REDIS_PORT'));
    if (getenv('REDIS_PASSWORD')) {
        $redis->auth(getenv('REDIS_PASSWORD'));
    }
    $redis->ping();
    echo "✅ Redis connection: OK\n";
} catch (Exception $e) {
    $errors[] = "❌ Redis connection failed: " . $e->getMessage();
}

// Display results
echo "\n" . str_repeat("=", 50) . "\n";
echo "VALIDATION RESULTS\n";
echo str_repeat("=", 50) . "\n";

if (!empty($warnings)) {
    echo "\nWarnings:\n";
    foreach ($warnings as $warning) {
        echo $warning . "\n";
    }
}

if (!empty($errors)) {
    echo "\nErrors:\n";
    foreach ($errors as $error) {
        echo $error . "\n";
    }
    echo "\n❌ Environment validation failed!\n";
    exit(1);
} else {
    echo "\n✅ Environment validation passed!\n";
    exit(0);
}