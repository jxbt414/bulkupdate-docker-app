<?php

namespace App\Console\Commands;

use App\Services\GoogleAdManagerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log as LaravelLog;

class TestLineItemUpdate extends Command
{
    protected $signature = 'test:line-item-update';
    protected $description = 'Test line item update with data from test_update.csv';

    public function handle()
    {
        try {
            $service = new GoogleAdManagerService();
            
            // Read data from test_update.csv
            $csvPath = base_path('test_update.csv');
            $csvFile = fopen($csvPath, 'r');
            
            if (!$csvFile) {
                throw new \Exception("Could not open CSV file: {$csvPath}");
            }
            
            $headers = fgetcsv($csvFile);
            $successCount = 0;
            
            $this->info("Starting line item update...");
            
            while (($row = fgetcsv($csvFile)) !== false) {
                $data = array_combine($headers, $row);
                
                // Add budget field which is required by the service
                $data['budget'] = '23'; // Default budget value
                
                $this->info("Updating line item: {$data['line_item_id']} with priority: {$data['priority']}, impression goals: {$data['impression_goals']}");
                
                // Update the line item and capture the response
                $updatedLineItem = $service->updateLineItem($data, 1);
                
                // Output detailed information about the updated line item
                $this->info("=== UPDATED LINE ITEM DETAILS ===");
                $this->info("ID: " . $updatedLineItem->getId());
                $this->info("Name: " . $updatedLineItem->getName());
                $this->info("Status: " . $updatedLineItem->getStatus());
                $this->info("Type: " . $updatedLineItem->getLineItemType());
                $this->info("Priority: " . $updatedLineItem->getPriority());
                
                // Budget information
                if ($updatedLineItem->getBudget()) {
                    $this->info("Budget Currency: " . $updatedLineItem->getBudget()->getCurrencyCode());
                    $this->info("Budget Micro Amount: " . $updatedLineItem->getBudget()->getMicroAmount());
                    $this->info("Budget (Converted): " . ($updatedLineItem->getBudget()->getMicroAmount() / 1000000));
                } else {
                    $this->info("Budget: Not set");
                }
                
                // Goal information
                if ($updatedLineItem->getPrimaryGoal()) {
                    $this->info("Goal Type: " . $updatedLineItem->getPrimaryGoal()->getGoalType());
                    $this->info("Goal Units: " . $updatedLineItem->getPrimaryGoal()->getUnits());
                } else {
                    $this->info("Goal: Not set");
                }
                
                // Dump the entire object for reference
                $this->info("=== FULL LINE ITEM OBJECT ===");
                $this->line(print_r($updatedLineItem, true));
                
                $successCount++;
            }
            
            fclose($csvFile);
            $this->info("Line item update completed successfully. Updated {$successCount} line items.");
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            $this->error("Stack trace:\n" . $e->getTraceAsString());
            return 1;
        }
        return 0;
    }
} 