<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\GoogleAdManagerService;

try {
    $service = new GoogleAdManagerService();
    
    // Data from test_update.csv
    $data = [
        'line_item_id' => '6342804872',
        'budget' => '23',
        'priority' => '10',
        'impression_goals' => '300'
    ];
    
    echo "Starting line item update...\n";
    $service->updateLineItem($data, 1);
    echo "Line item update completed successfully\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
} 