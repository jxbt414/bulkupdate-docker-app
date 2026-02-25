<?php

require __DIR__ . '/vendor/autoload.php';

use App\Services\CmsMetadataService;
use App\Services\GoogleAdManagerService;
use Illuminate\Support\Facades\Log;

// Bootstrap Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Testing CmsMetadataService class...\n";

try {
    // Get the GoogleAdManagerService instance
    $googleAdManagerService = app(GoogleAdManagerService::class);
    
    // Get the session from the GoogleAdManagerService
    $session = $googleAdManagerService->getSession();
    
    // Create an instance of CmsMetadataService
    $cmsMetadataService = new CmsMetadataService($session);
    
    echo "CmsMetadataService class instantiated successfully!\n";
    echo "Class: " . get_class($cmsMetadataService) . "\n";
    
    // Get CMS metadata keys
    $keys = $cmsMetadataService->searchMetadata();
    
    echo "Found " . count($keys) . " CMS metadata keys\n";
    
    if (!empty($keys)) {
        echo "First key: " . json_encode($keys[0]) . "\n";
        
        // Get CMS metadata values for the first key
        $keyId = $keys[0]['id'];
        $values = $cmsMetadataService->searchMetadataValues($keyId);
        
        echo "Found " . count($values) . " CMS metadata values for key ID " . $keyId . "\n";
        
        if (!empty($values)) {
            echo "First value: " . json_encode($values[0]) . "\n";
            
            // Get the raw CMS metadata value object to inspect its methods
            $statement = new Google\AdsApi\AdManager\v202411\Statement();
            $statement->setQuery("WHERE cmsMetadataKeyId = " . $keyId . " LIMIT 1");
            
            $serviceFactory = new Google\AdsApi\AdManager\v202411\ServiceFactory();
            $service = $serviceFactory->createCmsMetadataService($session);
            $response = $service->getCmsMetadataValuesByStatement($statement);
            
            if ($response->getResults() && count($response->getResults()) > 0) {
                $value = $response->getResults()[0];
                echo "Raw CMS metadata value object methods:\n";
                $methods = get_class_methods(get_class($value));
                echo implode("\n", $methods) . "\n";
            }
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
} 