<?php

require __DIR__ . '/vendor/autoload.php';

use App\Services\GoogleAdManagerService;
use Google\AdsApi\AdManager\v202411\Statement;
use Google\AdsApi\AdManager\v202411\ServiceFactory;
use Illuminate\Support\Facades\Log;

// Bootstrap Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Testing CMS Metadata methods...\n";

try {
    // Get the GoogleAdManagerService instance
    $googleAdManagerService = app(GoogleAdManagerService::class);
    
    // Get the session from the GoogleAdManagerService
    $session = $googleAdManagerService->getSession();
    
    // Create a service factory
    $serviceFactory = new ServiceFactory();
    
    // Create a CMS metadata service
    $cmsMetadataService = $serviceFactory->createCmsMetadataService($session);
    
    echo "CmsMetadataService created successfully!\n";
    
    // Get CMS metadata keys
    $statement = new Statement();
    $statement->setQuery("LIMIT 1");
    
    $response = $cmsMetadataService->getCmsMetadataKeysByStatement($statement);
    $keys = $response->getResults();
    
    if ($keys && count($keys) > 0) {
        $key = $keys[0];
        echo "CmsMetadataKey methods:\n";
        $methods = get_class_methods(get_class($key));
        echo implode("\n", $methods) . "\n\n";
        
        // Try to get values for this key
        $keyId = $key->getId();
        echo "Key ID: " . $keyId . "\n";
        
        $statement = new Statement();
        $statement->setQuery("WHERE cmsKeyId = " . $keyId . " LIMIT 1");
        
        $response = $cmsMetadataService->getCmsMetadataValuesByStatement($statement);
        $values = $response->getResults();
        
        if ($values && count($values) > 0) {
            $value = $values[0];
            echo "CmsMetadataValue methods:\n";
            $methods = get_class_methods(get_class($value));
            echo implode("\n", $methods) . "\n";
        } else {
            echo "No CMS metadata values found for key ID " . $keyId . "\n";
        }
    } else {
        echo "No CMS metadata keys found\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
} 