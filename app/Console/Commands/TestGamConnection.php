<?php

namespace App\Console\Commands;

use App\Services\GoogleAdManagerService;
use Google\AdsApi\AdManager\v202411\Statement;
use Illuminate\Console\Command;
use Exception;
use Illuminate\Support\Facades\Log;

class TestGamConnection extends Command
{
    protected $signature = 'gam:test';
    protected $description = 'Test Google Ad Manager connection and retrieve line items';

    private ?GoogleAdManagerService $gamService;

    public function __construct(?GoogleAdManagerService $gamService = null)
    {
        parent::__construct();
        $this->gamService = $gamService;
    }

    public function handle()
    {
        if ($this->gamService === null) {
            $this->error('Google Ad Manager service is not available. Check your credentials configuration.');
            return 1;
        }

        $this->info('Testing Google Ad Manager connection...');

        try {
            // Test connection by retrieving a few line items
            $statement = new Statement();
            $statement->setQuery('LIMIT 5');
            
            $this->info('Executing query: ' . $statement->getQuery());
            
            $result = $this->gamService->getLineItemService()->getLineItemsByStatement($statement);
            
            if ($result->getResults() === null || count($result->getResults()) === 0) {
                $this->warn('No line items found in the account.');
                return;
            }

            $this->info('Successfully connected to Google Ad Manager!');
            $this->info('Found ' . count($result->getResults()) . ' line items:');

            foreach ($result->getResults() as $lineItem) {
                $this->line(sprintf(
                    'Line Item ID: %s, Name: %s, Status: %s',
                    $lineItem->getId(),
                    $lineItem->getName(),
                    $lineItem->getStatus()
                ));
                
                // Log more details about each line item
                Log::debug('Line Item Details', [
                    'id' => $lineItem->getId(),
                    'name' => $lineItem->getName(),
                    'status' => $lineItem->getStatus(),
                    'orderId' => $lineItem->getOrderId(),
                    'startDateTime' => $lineItem->getStartDateTime(),
                    'endDateTime' => $lineItem->getEndDateTime()
                ]);
            }

        } catch (Exception $e) {
            $this->error('Failed to connect to Google Ad Manager:');
            $this->error($e->getMessage());
            $this->error('Stack trace:');
            $this->error($e->getTraceAsString());
            
            // Log the full error details
            Log::error('Google Ad Manager Connection Error', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
} 