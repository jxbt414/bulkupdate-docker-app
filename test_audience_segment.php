<?php

require __DIR__ . '/vendor/autoload.php';

use App\Services\AudienceSegmentService;
use App\Services\GoogleAdManagerService;
use Illuminate\Support\Facades\App;

// Bootstrap Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    echo "Testing AudienceSegmentService class...\n";
    
    // Get the GoogleAdManagerService instance
    $googleAdManagerService = app(GoogleAdManagerService::class);
    
    // Get the session from the GoogleAdManagerService
    $session = $googleAdManagerService->getSession();
    
    // Create an instance of AudienceSegmentService
    $audienceSegmentService = new AudienceSegmentService($session);
    
    echo "AudienceSegmentService class instantiated successfully!\n";
    echo "Class: " . get_class($audienceSegmentService) . "\n";
    
    // Try to call a method
    $segments = $audienceSegmentService->searchSegments('');
    
    echo "Found " . count($segments) . " audience segments\n";
    
    if (count($segments) > 0) {
        echo "First segment: " . json_encode($segments[0]) . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
} 