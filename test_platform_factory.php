<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

$factory = $app->make(\App\Services\PlatformConnectorFactory::class);

echo "Available platforms: " . implode(', ', $factory->getAvailablePlatforms()) . PHP_EOL;
echo "Statistics: " . json_encode($factory->getStatistics(), JSON_PRETTY_PRINT) . PHP_EOL;

// Test creating connectors
foreach ($factory->getAvailablePlatforms() as $platform) {
    try {
        $connector = $factory->create($platform);
        echo "✓ {$platform} connector created successfully" . PHP_EOL;
        
        $schema = $connector->getConfigurationSchema();
        echo "  Configuration fields: " . implode(', ', array_keys($schema)) . PHP_EOL;
    } catch (Exception $e) {
        echo "✗ {$platform} connector failed: " . $e->getMessage() . PHP_EOL;
    }
}