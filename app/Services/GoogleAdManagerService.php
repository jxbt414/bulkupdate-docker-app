<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LineItem;
use App\Models\Log;
use App\Models\Rollback;
use Exception;
use Illuminate\Support\Facades\Log as LaravelLog;
use Google\AdsApi\AdManager\AdManagerSession;
use Google\AdsApi\AdManager\AdManagerSessionBuilder;
use Google\AdsApi\AdManager\v202411\LineItemService;
use Google\AdsApi\AdManager\v202411\LabelService;
use Google\AdsApi\AdManager\v202411\ServiceFactory;
use Google\AdsApi\AdManager\v202411\Statement;
use Google\AdsApi\AdManager\v202411\Money;
use Google\AdsApi\AdManager\v202411\Goal;
use Google\AdsApi\AdManager\v202411\Targeting;
use Google\AdsApi\Common\Configuration;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\AdsApi\AdManager\v202411\RequestPlatformTargeting;
use Google\AdsApi\AdManager\v202411\InventoryTargeting;
use Google\AdsApi\AdManager\v202411\LineItem as GAMLineItem;
use Google\AdsApi\AdManager\v202411\VideoMaxDuration;
use Google\AdsApi\AdManager\v202411\RequestPlatform;
use Google\AdsApi\AdManager\v202411\InventoryService;
use Google\AdsApi\AdManager\v202411\PlacementService;
use App\Services\CustomTargetingService;
use Google\AdsApi\Common\OAuth2TokenBuilder;
use Google\AdsApi\AdManager\AdManagerSession as Session;
use Google\AdsApi\AdManager\v202411\AudienceSegmentService;
use Google\AdsApi\AdManager\v202411\CmsMetadataService;
use Google\AdsApi\AdManager\v202411\StatementBuilder;
use Google\AdsApi\AdManager\v202411\NumberValue;
use Google\AdsApi\AdManager\v202411\ValueMapEntry;
use Google\AdsApi\AdManager\v202411\TextValue;
use Google\AdsApi\AdManager\v202411\GeoTargeting;
use Google\AdsApi\AdManager\v202411\Location;

class GoogleAdManagerService
{
    private $session;
    private $serviceFactory;
    private ?LabelService $labelService = null;
    private ?LineItemService $lineItemService = null;
    private ?InventoryService $inventoryService = null;
    private ?PlacementService $placementService = null;
    private ?\Google\AdsApi\AdManager\v202411\CustomTargetingService $customTargetingService = null;
    private const NETWORK_CODE = '21780812979';
    private const APPLICATION_NAME = 'Bulk Update Tool';
    
    public function __construct(Session $session)
    {
        $this->session = $session;
        $this->serviceFactory = new ServiceFactory();
        
        try {
            // Enable SOAP logging
            ini_set('soap.wsdl_cache_enabled', 0);
            if (!file_exists(storage_path('logs'))) {
                mkdir(storage_path('logs'), 0755, true);
            }
            $soapLog = fopen(storage_path('logs/soap.log'), 'a');
            LaravelLog::info("SOAP logging enabled");
            
            // Get the service
            $this->lineItemService = $this->serviceFactory->createLineItemService($this->session);
            
            LaravelLog::info("Successfully created LineItemService");
        } catch (Exception $e) {
            LaravelLog::error('Failed to initialize Google Ad Manager client: ' . $e->getMessage());
            LaravelLog::error('Stack trace: ' . $e->getTraceAsString());
            throw new Exception('Failed to initialize Google Ad Manager client: ' . $e->getMessage());
        }
    }

    public function getLabelService(): LabelService
    {
        if ($this->labelService === null) {
            $this->labelService = $this->serviceFactory->createLabelService($this->session);
            LaravelLog::info("Created LabelService");
        }
        return $this->labelService;
    }

    public function ensureLineItemExists(string $lineItemId, string $lineItemName = null): void
    {
        try {
            LineItem::firstOrCreate(
                ['line_item_id' => $lineItemId],
                [
                    'line_item_name' => $lineItemName ?? $lineItemId,
                    'budget' => 0,
                    'priority' => 0
                ]
            );
        } catch (Exception $e) {
            LaravelLog::error("Failed to ensure line item exists in database: " . $e->getMessage());
            throw new Exception("Failed to ensure line item exists in database: " . $e->getMessage());
        }
    }

    public function updateLineItem(array $data, int $userId): GAMLineItem
    {
        try {
            LaravelLog::info("Starting line item update process", [
                'line_item_id' => $data['line_item_id'],
                'user_id' => $userId,
                'update_data' => $data
            ]);

            // Get the current line item
            $statement = new Statement();
            $statement->setQuery("WHERE ID = {$data['line_item_id']}");
            
            LaravelLog::info("Fetching line item from GAM API", ['line_item_id' => $data['line_item_id']]);
            $result = $this->lineItemService->getLineItemsByStatement($statement);
            
            if ($result->getResults() === null || count($result->getResults()) === 0) {
                throw new Exception("Line item not found: {$data['line_item_id']}");
            }
            
            /** @var GAMLineItem $lineItem */
            $lineItem = $result->getResults()[0];
            $isCompleted = $lineItem->getStatus() === 'COMPLETED';
            
            $originalState = [
                'name' => $lineItem->getName(),
                'status' => $lineItem->getStatus(),
                'budget' => $lineItem->getBudget() ? [
                    'currencyCode' => $lineItem->getBudget()->getCurrencyCode(),
                    'microAmount' => $lineItem->getBudget()->getMicroAmount()
                ] : null,
                'priority' => $lineItem->getPriority(),
                'impression_goals' => $lineItem->getPrimaryGoal() ? $lineItem->getPrimaryGoal()->getUnits() : null,
                'goal_type' => $lineItem->getPrimaryGoal() ? $lineItem->getPrimaryGoal()->getGoalType() : null,
                'video_max_duration' => $lineItem->getVideoMaxDuration() ? $lineItem->getVideoMaxDuration() : null
            ];
            
            LaravelLog::info("Retrieved line item from GAM API", [
                'line_item_id' => $lineItem->getId(),
                'current_state' => $originalState
            ]);
            
            // Store current state for rollback
            $this->storeRollbackData($data['line_item_id']);
            
            // Update line item fields and get the updated line item
            $updatedLineItem = $this->updateLineItemFields($lineItem, $data);
            
            // Make the API call to update the line item
            $result = $this->lineItemService->updateLineItems([$updatedLineItem]);
            
            if ($result === null || empty($result)) {
                throw new Exception("Failed to update line item: {$data['line_item_id']}");
            }
            
            /** @var GAMLineItem $verifiedLineItem */
            $verifiedLineItem = $result[0];
            
            // Verify the updates
            $actualUpdates = [
                'name' => $verifiedLineItem->getName(),
                'status' => $verifiedLineItem->getStatus(),
                'budget' => $verifiedLineItem->getBudget() ? [
                    'currencyCode' => $verifiedLineItem->getBudget()->getCurrencyCode(),
                    'microAmount' => $verifiedLineItem->getBudget()->getMicroAmount()
                    ] : null,
                'priority' => $verifiedLineItem->getPriority(),
                'impression_goals' => $verifiedLineItem->getPrimaryGoal() ? $verifiedLineItem->getPrimaryGoal()->getUnits() : null,
                'goal_type' => $verifiedLineItem->getPrimaryGoal() ? $verifiedLineItem->getPrimaryGoal()->getGoalType() : null,
                'video_max_duration' => $verifiedLineItem->getVideoMaxDuration() ? $verifiedLineItem->getVideoMaxDuration() : null
            ];
            
            // Check if updates were applied correctly
            $updateErrors = [];
            
            if (isset($data['budget']) && !$isCompleted) {
                $expectedMicroAmount = (int)(floatval($data['budget']) * 1000000);
                $actualMicroAmount = $verifiedLineItem->getBudget() ? $verifiedLineItem->getBudget()->getMicroAmount() : 0;
                if ($actualMicroAmount !== $expectedMicroAmount) {
                    $updateErrors[] = "Budget not updated correctly. Expected: {$expectedMicroAmount}, Actual: {$actualMicroAmount}";
                }
            }
            
            if (isset($data['priority']) && $verifiedLineItem->getPriority() != $data['priority']) {
                $updateErrors[] = "Priority not updated correctly. Expected: {$data['priority']}, Actual: {$verifiedLineItem->getPriority()}";
            }
            
            if (isset($data['impression_goals'])) {
                $expectedGoals = (int)$data['impression_goals'];
                // For SPONSORSHIP line items, impression goals cannot exceed 100%
                if ($verifiedLineItem->getLineItemType() === 'SPONSORSHIP' && $expectedGoals > 100) {
                    $expectedGoals = 100;
                }
                $actualGoals = $verifiedLineItem->getPrimaryGoal() ? $verifiedLineItem->getPrimaryGoal()->getUnits() : null;
                if ($actualGoals !== $expectedGoals) {
                    $updateErrors[] = "Impression goals not updated correctly. Expected: {$expectedGoals}, Actual: {$actualGoals}";
                }
            }
            
            if (!empty($updateErrors)) {
                $errorMessage = "Line item update verification failed: " . implode("; ", $updateErrors);
                LaravelLog::error($errorMessage, [
                    'line_item_id' => $data['line_item_id'],
                    'original_state' => $originalState,
                    'intended_updates' => $data,
                    'actual_updates' => $actualUpdates
                ]);
                throw new Exception($errorMessage);
            }
            
            LaravelLog::info("Line item update successful and verified", [
                'line_item_id' => $data['line_item_id'],
                'original_state' => $originalState,
                'intended_updates' => $data,
                'actual_updates' => $actualUpdates
            ]);

            return $verifiedLineItem;
        } catch (Exception $e) {
            LaravelLog::error("Failed to update line item: " . $e->getMessage(), [
                'line_item_id' => $data['line_item_id'],
                'user_id' => $userId,
                'update_data' => $data,
                'stack_trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function storeRollbackData(string $lineItemId): void
    {
        try {
            $statement = new Statement();
            $statement->setQuery("WHERE ID  = {$lineItemId}");
            
            $result = $this->lineItemService->getLineItemsByStatement($statement);
            if ($result->getResults() === null || count($result->getResults()) === 0) {
                throw new Exception("Line item not found: {$lineItemId}");
            }
            
            /** @var GAMLineItem $lineItem */
            $lineItem = $result->getResults()[0];
            
            // Convert the line item to an array, handling potential serialization issues
            $lineItemData = [];
            try {
                // Extract all necessary fields for rollback
                $lineItemData = [
                    'id' => $lineItem->getId(),
                    'name' => $lineItem->getName(),
                    'status' => $lineItem->getStatus(),
                    'lineItemType' => $lineItem->getLineItemType(),
                    'priority' => $lineItem->getPriority(),
                    'budget' => $lineItem->getBudget() ? [
                        'currencyCode' => $lineItem->getBudget()->getCurrencyCode(),
                        'microAmount' => $lineItem->getBudget()->getMicroAmount()
                    ] : null,
                    'startDateTime' => $lineItem->getStartDateTime() ? $lineItem->getStartDateTime()->getDate() : null,
                    'endDateTime' => $lineItem->getEndDateTime() ? $lineItem->getEndDateTime()->getDate() : null,
                    // Add primary goal data
                    'primaryGoal' => $lineItem->getPrimaryGoal() ? [
                        'units' => $lineItem->getPrimaryGoal()->getUnits(),
                        'goalType' => $lineItem->getPrimaryGoal()->getGoalType()
                    ] : null,
                    // Add targeting data
                    'targeting' => $lineItem->getTargeting() ? [
                        'requestPlatformTargeting' => $lineItem->getTargeting()->getRequestPlatformTargeting() ? [
                            'targetedRequestPlatforms' => $lineItem->getTargeting()->getRequestPlatformTargeting()->getTargetedRequestPlatforms()
                        ] : null,
                        'inventoryTargeting' => $lineItem->getTargeting()->getInventoryTargeting() ? true : null,
                        'geoTargeting' => $lineItem->getTargeting()->getGeoTargeting() ? true : null,
                        'dayPartTargeting' => $lineItem->getTargeting()->getDayPartTargeting() ? true : null,
                        'technologyTargeting' => $lineItem->getTargeting()->getTechnologyTargeting() ? true : null,
                        'customTargeting' => $lineItem->getTargeting()->getCustomTargeting() ? true : null
                    ] : null
                ];
            } catch (Exception $e) {
                LaravelLog::warning("Some line item fields could not be serialized: " . $e->getMessage());
            }
            
            Rollback::create([
                'line_item_id' => $lineItemId,
                'previous_data' => $lineItemData,
                'rollback_timestamp' => now()
            ]);
        } catch (Exception $e) {
            LaravelLog::error("Failed to store rollback data for line item {$lineItemId}: " . $e->getMessage());
            throw new Exception("Failed to store rollback data: " . $e->getMessage());
        }
    }

    private function updateLineItemFields(GAMLineItem $lineItem, array $data): GAMLineItem
    {
        // Clone the original line item
        $updatedLineItem = clone $lineItem;
        
        // Log the initial state
        LaravelLog::info('Initial line item state:', [
            'line_item_id' => $lineItem->getId(),
            'delivery_rate_type' => $lineItem->getDeliveryRateType(),
            'update_data' => $data
        ]);
        
        // Check if line item is in COMPLETED status
        $isCompleted = $lineItem->getStatus() === 'COMPLETED';
        if ($isCompleted) {
            LaravelLog::warning('Line item is in COMPLETED status. Some fields like budget cannot be updated.', [
                'line_item_id' => $lineItem->getId(),
                'status' => $lineItem->getStatus()
            ]);
        }
        
        // Preserve all fields that are not being updated
        if (!isset($data['budget']) && $lineItem->getBudget() !== null) {
            $originalBudget = new Money();
            $originalBudget->setCurrencyCode($lineItem->getBudget()->getCurrencyCode());
            $originalBudget->setMicroAmount($lineItem->getBudget()->getMicroAmount());
            $updatedLineItem->setBudget($originalBudget);
            LaravelLog::info('Preserving original budget', [
                'line_item_id' => $lineItem->getId(),
                'budget_micro_amount' => $originalBudget->getMicroAmount(),
                'currency_code' => $originalBudget->getCurrencyCode()
            ]);
        } else if (isset($data['budget'])) {
            if ($isCompleted) {
                // For COMPLETED line items, preserve the original budget and log a warning
                $originalBudget = new Money();
                $originalBudget->setCurrencyCode($lineItem->getBudget() ? $lineItem->getBudget()->getCurrencyCode() : 'AUD');
                $originalBudget->setMicroAmount($lineItem->getBudget() ? $lineItem->getBudget()->getMicroAmount() : 0);
                $updatedLineItem->setBudget($originalBudget);
                
                LaravelLog::warning('Cannot update budget for COMPLETED line item. Preserving original budget.', [
                    'line_item_id' => $lineItem->getId(),
                    'requested_budget' => $data['budget'],
                    'original_budget_micro_amount' => $originalBudget->getMicroAmount()
                ]);
                
                // Remove budget from data to prevent verification errors
                unset($data['budget']);
            } else {
                $currencyCode = $lineItem->getBudget() ? $lineItem->getBudget()->getCurrencyCode() : 'AUD';
                $money = new Money();
                $money->setCurrencyCode($currencyCode);
                // Convert budget to float and multiply by 1,000,000 to get microAmount
                $microAmount = (int)(floatval($data['budget']) * 1000000);
                $money->setMicroAmount($microAmount);
                $updatedLineItem->setBudget($money);
                LaravelLog::info('Setting new budget', [
                    'line_item_id' => $lineItem->getId(),
                    'budget' => $data['budget'],
                    'budget_micro_amount' => $microAmount,
                    'currency_code' => $currencyCode
                ]);
            }
        }

        if (!isset($data['priority'])) {
            $updatedLineItem->setPriority($lineItem->getPriority());
            LaravelLog::info('Preserving original priority', [
                'line_item_id' => $lineItem->getId(),
                'priority' => $lineItem->getPriority()
            ]);
        } else {
            // Allow priority updates even for COMPLETED line items
            $priority = (int)$data['priority'];
            $updatedLineItem->setPriority($priority);
            LaravelLog::info('Setting new priority', [
                'line_item_id' => $lineItem->getId(),
                'priority' => $priority
            ]);
        }

        if (!isset($data['video_max_duration']) && $lineItem->getVideoMaxDuration() !== null) {
            $updatedLineItem->setVideoMaxDuration($lineItem->getVideoMaxDuration());
            LaravelLog::info('Preserving original video max duration', [
                'line_item_id' => $lineItem->getId(),
                'video_max_duration' => $lineItem->getVideoMaxDuration()
            ]);
        } else if (isset($data['video_max_duration'])) {
            $updatedLineItem->setVideoMaxDuration((int)$data['video_max_duration']);
            LaravelLog::info('Setting new video max duration', [
                'line_item_id' => $lineItem->getId(),
                'video_max_duration' => (int)$data['video_max_duration']
            ]);
        }
        
        // Get existing targeting to preserve required settings
        $existingTargeting = $updatedLineItem->getTargeting();
        if ($existingTargeting === null) {
            $existingTargeting = new Targeting();
        }

        // Create new targeting object
        $targeting = new Targeting();

        // REQUIRED: Preserve request platform targeting
        $requestPlatformTargeting = $existingTargeting->getRequestPlatformTargeting();
        if ($requestPlatformTargeting === null) {
            LaravelLog::warning("Creating default request platform targeting");
            $requestPlatformTargeting = new RequestPlatformTargeting();
        }
        $targeting->setRequestPlatformTargeting($requestPlatformTargeting);

        // REQUIRED: Preserve inventory targeting
        $inventoryTargeting = $existingTargeting->getInventoryTargeting();
        if ($inventoryTargeting === null) {
            LaravelLog::warning("Creating default inventory targeting");
            $inventoryTargeting = new InventoryTargeting();
        }
        $targeting->setInventoryTargeting($inventoryTargeting);

        // Handle CMS metadata targeting
        if (isset($data['cms_metadata']) && !empty($data['cms_metadata'])) {
            LaravelLog::info('Setting CMS metadata targeting', [
                'line_item_id' => $lineItem->getId(),
                'cms_metadata' => $data['cms_metadata']
            ]);

            $customCriteriaSet = new \Google\AdsApi\AdManager\v202411\CustomCriteriaSet();
            $customCriteriaSet->setLogicalOperator('AND');

            $cmsMetadataCriteria = [];
            foreach ($data['cms_metadata'] as $metadata) {
                $criterion = new \Google\AdsApi\AdManager\v202411\CmsMetadataCriteria();
                $criterion->setCmsMetadataValueIds([$metadata['value']]);
                $criterion->setOperator($metadata['operator']);
                $cmsMetadataCriteria[] = $criterion;
            }

            $customCriteriaSet->setChildren($cmsMetadataCriteria);
            $targeting->setCustomTargeting($customCriteriaSet);
        } else if ($existingTargeting->getCustomTargeting() !== null) {
            $targeting->setCustomTargeting($existingTargeting->getCustomTargeting());
        }

        // Set the targeting on the line item
        $updatedLineItem->setTargeting($targeting);

        // Handle impression goals
        if (isset($data['impression_goals'])) {
            // Allow impression goal updates even for COMPLETED line items
            $goal = new Goal();
            $goal->setGoalType($lineItem->getPrimaryGoal()->getGoalType());
            $goal->setUnitType($lineItem->getPrimaryGoal()->getUnitType());
            
            // For SPONSORSHIP line items, impression goals cannot exceed 100%
            $units = (int)$data['impression_goals'];
            if ($lineItem->getLineItemType() === 'SPONSORSHIP' && $units > 100) {
                $units = 100;
                LaravelLog::warning('Capping impression goals to 100 for SPONSORSHIP line item', [
                    'line_item_id' => $lineItem->getId(),
                    'requested_goals' => $data['impression_goals'],
                    'capped_goals' => $units
                ]);
            }
            
            $goal->setUnits($units);
            $updatedLineItem->setPrimaryGoal($goal);
            LaravelLog::info('Setting new impression goals', [
                'line_item_id' => $lineItem->getId(),
                'impression_goals' => $units
            ]);
        } else if ($lineItem->getPrimaryGoal() !== null) {
            $originalGoal = new Goal();
            $originalGoal->setGoalType($lineItem->getPrimaryGoal()->getGoalType());
            $originalGoal->setUnitType($lineItem->getPrimaryGoal()->getUnitType());
            $originalGoal->setUnits($lineItem->getPrimaryGoal()->getUnits());
            $updatedLineItem->setPrimaryGoal($originalGoal);
            LaravelLog::info('Preserving original impression goals', [
                'line_item_id' => $lineItem->getId(),
                'impression_goals' => $originalGoal->getUnits()
            ]);
        }

        // Handle delivery rate type
        if (isset($data['delivery_rate_type'])) {
            LaravelLog::info('Setting new delivery rate type', [
                'line_item_id' => $lineItem->getId(),
                'original_delivery_rate_type' => $lineItem->getDeliveryRateType(),
                'new_delivery_rate_type' => $data['delivery_rate_type']
            ]);
            $updatedLineItem->setDeliveryRateType($data['delivery_rate_type']);
            
            // Verify the update was set correctly on the object
            LaravelLog::info('Verifying delivery rate type update on object', [
                'line_item_id' => $lineItem->getId(),
                'set_delivery_rate_type' => $updatedLineItem->getDeliveryRateType(),
                'expected_delivery_rate_type' => $data['delivery_rate_type']
            ]);
        } else {
            LaravelLog::info('Preserving original delivery rate type', [
                'line_item_id' => $lineItem->getId(),
                'delivery_rate_type' => $lineItem->getDeliveryRateType()
            ]);
            $updatedLineItem->setDeliveryRateType($lineItem->getDeliveryRateType());
        }

        // Handle geo targeting
        if (isset($data['geo_targeting_included_add']) || 
            isset($data['geo_targeting_included_remove']) || 
            isset($data['geo_targeting_excluded_add']) || 
            isset($data['geo_targeting_excluded_remove'])) {
            
            // Get current targeting or create new
            $targeting = $lineItem->getTargeting() ?? new Targeting();
            $geoTargeting = $targeting->getGeoTargeting() ?? new GeoTargeting();
            
            // Get current included/excluded locations
            $includedLocations = $geoTargeting->getTargetedLocations() ?? [];
            $excludedLocations = $geoTargeting->getExcludedLocations() ?? [];

            // Handle included locations additions
            if (!empty($data['geo_targeting_included_add'])) {
                $locationsToAdd = array_map('trim', explode(',', $data['geo_targeting_included_add']));
                foreach ($locationsToAdd as $locationId) {
                    $location = new Location();
                    $location->setId(intval($locationId));
                    $includedLocations[] = $location;
                }
            }

            // Handle included locations removals
            if (!empty($data['geo_targeting_included_remove'])) {
                $locationsToRemove = array_map('trim', explode(',', $data['geo_targeting_included_remove']));
                $includedLocations = array_filter($includedLocations, function($location) use ($locationsToRemove) {
                    return !in_array($location->getId(), $locationsToRemove);
                });
            }

            // Handle excluded locations additions
            if (!empty($data['geo_targeting_excluded_add'])) {
                $locationsToAdd = array_map('trim', explode(',', $data['geo_targeting_excluded_add']));
                foreach ($locationsToAdd as $locationId) {
                    $location = new Location();
                    $location->setId(intval($locationId));
                    $excludedLocations[] = $location;
                }
            }

            // Handle excluded locations removals
            if (!empty($data['geo_targeting_excluded_remove'])) {
                $locationsToRemove = array_map('trim', explode(',', $data['geo_targeting_excluded_remove']));
                $excludedLocations = array_filter($excludedLocations, function($location) use ($locationsToRemove) {
                    return !in_array($location->getId(), $locationsToRemove);
                });
            }

            // Update geo targeting
            $geoTargeting->setTargetedLocations(array_values($includedLocations));
            $geoTargeting->setExcludedLocations(array_values($excludedLocations));
            $targeting->setGeoTargeting($geoTargeting);
            $lineItem->setTargeting($targeting);

            LaravelLog::info('Updated geo targeting', [
                'line_item_id' => $lineItem->getId(),
                'included_locations' => array_map(function($loc) { return $loc->getId(); }, $includedLocations),
                'excluded_locations' => array_map(function($loc) { return $loc->getId(); }, $excludedLocations)
            ]);
        }

        // Make the API call to update the line item
        try {
            LaravelLog::info('Making API call to update line item', [
                'line_item_id' => $lineItem->getId(),
                'update_data' => $data,
                'delivery_rate_type_before_call' => $updatedLineItem->getDeliveryRateType()
            ]);

            $result = $this->lineItemService->updateLineItems([$updatedLineItem]);
            
            if ($result === null || empty($result)) {
                throw new Exception('No response from GAM API when updating line item');
            }

            LaravelLog::info('Line item update API call successful', [
                'line_item_id' => $lineItem->getId(),
                'delivery_rate_type_after_update' => $result[0]->getDeliveryRateType(),
                'response' => $result
            ]);

            return $result[0];
        } catch (Exception $e) {
            LaravelLog::error('Failed to update line item via API', [
                'line_item_id' => $lineItem->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function rollback(string $lineItemId, int $userId): void
    {
        try {
            // Ensure line item exists in database before proceeding
            $this->ensureLineItemExists($lineItemId);

            $rollback = Rollback::where('line_item_id', $lineItemId)
                ->latest('rollback_timestamp')
                ->firstOrFail();

            $statement = new Statement();
            $statement->setQuery("WHERE ID = {$lineItemId}");
            
            $result = $this->lineItemService->getLineItemsByStatement($statement);
            if ($result->getResults() === null || count($result->getResults()) === 0) {
                throw new Exception("Line item not found: {$lineItemId}");
            }
            
            /** @var GAMLineItem $lineItem */
            $lineItem = $result->getResults()[0];
            
            // Get existing targeting to preserve required settings
            $existingTargeting = $lineItem->getTargeting();
            if ($existingTargeting === null) {
                LaravelLog::warning("No existing targeting found for line item {$lineItem->getId()}, creating new targeting");
                $existingTargeting = new Targeting();
            }
            
            // Restore previous state
            $previousData = $rollback->previous_data;
            
            if (isset($previousData['name'])) {
                $lineItem->setName($previousData['name']);
            }
            
            if (isset($previousData['status'])) {
                $lineItem->setStatus($previousData['status']);
            }
            
            if (isset($previousData['lineItemType'])) {
                $lineItem->setLineItemType($previousData['lineItemType']);
            }
            
            if (isset($previousData['priority'])) {
                $lineItem->setPriority($previousData['priority']);
            }
            
            if (isset($previousData['budget']) && $previousData['budget'] !== null) {
                $money = new Money();
                $money->setCurrencyCode($previousData['budget']['currencyCode']);
                $money->setMicroAmount($previousData['budget']['microAmount']);
                $lineItem->setBudget($money);
            }

            // Add impression goals rollback
            if (isset($previousData['primaryGoal']) && $previousData['primaryGoal'] !== null) {
                $goal = new Goal();
                $goal->setUnits($previousData['primaryGoal']['units']);
                $goal->setGoalType($previousData['primaryGoal']['goalType']);
                $lineItem->setPrimaryGoal($goal);
            }

            // Create new targeting object while preserving existing targeting settings
            $targeting = new Targeting();
            
            // REQUIRED: Preserve request platform targeting
            $requestPlatformTargeting = $existingTargeting->getRequestPlatformTargeting();
            if ($requestPlatformTargeting === null) {
                LaravelLog::warning("Creating default request platform targeting");
                $requestPlatformTargeting = new RequestPlatformTargeting();
            }
            $targeting->setRequestPlatformTargeting($requestPlatformTargeting);
            
            // REQUIRED: Preserve inventory targeting
            $inventoryTargeting = $existingTargeting->getInventoryTargeting();
            if ($inventoryTargeting === null) {
                LaravelLog::warning("Creating default inventory targeting");
                $inventoryTargeting = new InventoryTargeting();
            }
            $targeting->setInventoryTargeting($inventoryTargeting);
            
            // Preserve other targeting settings
            if ($existingTargeting->getGeoTargeting() !== null) {
                $targeting->setGeoTargeting($existingTargeting->getGeoTargeting());
            }
            if ($existingTargeting->getUserDomainTargeting() !== null) {
                $targeting->setUserDomainTargeting($existingTargeting->getUserDomainTargeting());
            }
            if ($existingTargeting->getDayPartTargeting() !== null) {
                $targeting->setDayPartTargeting($existingTargeting->getDayPartTargeting());
            }
            if ($existingTargeting->getTechnologyTargeting() !== null) {
                $targeting->setTechnologyTargeting($existingTargeting->getTechnologyTargeting());
            }
            if ($existingTargeting->getCustomTargeting() !== null) {
                $targeting->setCustomTargeting($existingTargeting->getCustomTargeting());
            }
            
            // Set the targeting on the line item
            $lineItem->setTargeting($targeting);

            // Perform the rollback
            $result = $this->lineItemService->updateLineItems([$lineItem]);

        } catch (Exception $e) {
            LaravelLog::error("Failed to rollback line item {$lineItemId}: " . $e->getMessage());
            throw $e;
        }
    }

    public function getLineItemService()
    {
        return $this->serviceFactory->createLineItemService($this->session);
    }

    public function setLineItemService(LineItemService $service): void
    {
        $this->lineItemService = $service;
    }

    public function getInventoryService(): InventoryService
    {
        if ($this->inventoryService === null) {
            LaravelLog::info('Creating new InventoryService instance');
            $this->inventoryService = $this->serviceFactory->createInventoryService($this->session);
        }
        return $this->inventoryService;
    }

    public function getPlacementService(): PlacementService
    {
        if ($this->placementService === null) {
            LaravelLog::info('Creating new PlacementService instance');
            $this->placementService = $this->serviceFactory->createPlacementService($this->session);
        }
        return $this->placementService;
    }

    public function searchAdUnits(string $query = null): array
    {
        try {
            $statement = new Statement();
            if ($query) {
                $statement->setQuery("WHERE name LIKE '%" . $query . "%' OR adUnitCode LIKE '%" . $query . "%' ORDER BY name ASC LIMIT 10");
            } else {
                $statement->setQuery("ORDER BY name ASC LIMIT 10");
            }
            
            $response = $this->getInventoryService()->getAdUnitsByStatement($statement);
            $adUnits = $response->getResults() ?? [];
            
            return array_map(function($adUnit) {
                // Get the full path except root
                $parentPath = $adUnit->getParentPath();
                $pathNames = array_map(function($parent) {
                    return $parent->getName();
                }, $parentPath);
                // Remove the first element (root)
                array_shift($pathNames);
                $path = implode(' > ', $pathNames);
                
                return [
                    'id' => $adUnit->getId(),
                    'name' => $adUnit->getName(),
                    'path' => $path ? $path . ' > ' . $adUnit->getName() : $adUnit->getName(),
                    'displayName' => $path ? $adUnit->getName() . ' (' . $path . ')' : $adUnit->getName()
                ];
            }, $adUnits);
        } catch (\Exception $e) {
            LaravelLog::error('Error searching ad units: ' . $e->getMessage());
            throw $e;
        }
    }

    public function searchPlacements(string $query = null): array
    {
        try {
            $service = $this->getPlacementService();
            $statement = new Statement();
            
            if ($query) {
                $statement->setQuery("WHERE name LIKE '%" . $query . "%' ORDER BY name ASC LIMIT 10");
            } else {
                $statement->setQuery("ORDER BY name ASC LIMIT 10");
            }
            
            $response = $service->getPlacementsByStatement($statement);
            
            if (!$response->getResults()) {
                return [];
            }
            
            return array_map(function($placement) {
                return [
                    'id' => $placement->getId(),
                    'name' => $placement->getName(),
                    'status' => $placement->getStatus(),
                    'description' => $placement->getDescription()
                ];
            }, $response->getResults());
        } catch (Exception $e) {
            LaravelLog::error('Failed to fetch placements: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Search for locations based on a search query
     * 
     * @param string|null $query The search query
     * @return array Array of location objects containing only Australian locations
     */
    public function searchLocations(string $query = null): array
    {
        try {
            // For now, we'll use the geotargets CSV file to search for locations
            // In a production environment, you would use the Google Ad Manager API
            $locations = [];
            
            if (!$query) {
                return $locations;
            }
            
            // Path to the geotargets CSV file
            $filePath = base_path('geotargets-2025-01-13.csv');
            
            if (!file_exists($filePath)) {
                LaravelLog::error('Geotargets CSV file not found: ' . $filePath);
                return $locations;
            }
            
            // Open the file with UTF-8 encoding
            $file = fopen($filePath, 'r');
            if (!$file) {
                LaravelLog::error('Failed to open geotargets CSV file');
                return $locations;
            }
            
            // Skip the header row
            fgetcsv($file);
            
            // Search for locations matching the query
            $count = 0;
            $limit = 20; // Limit the number of results
            $totalProcessed = 0;
            $australianLocationsFound = 0;
            
            LaravelLog::info('Starting location search', [
                'query' => $query,
                'country_filter' => 'AU'
            ]);
            
            while (($row = fgetcsv($file)) !== false && $count < $limit) {
                $totalProcessed++;
                
                // Only include Australian locations (country code 'AU')
                if (isset($row[4]) && $row[4] === 'AU') {
                    $australianLocationsFound++;
                    
                    // Check if the location name or canonical name contains the query
                    if (
                        (isset($row[1]) && stripos($row[1], $query) !== false) || 
                        (isset($row[2]) && stripos($row[2], $query) !== false)
                    ) {
                        $locations[] = [
                            'id' => $row[0] ?? '',
                            'name' => $row[1] ?? '',
                            'canonicalName' => $row[2] ?? '',
                            'countryCode' => $row[4] ?? '',
                            'targetType' => $row[5] ?? ''
                        ];
                        $count++;
                        
                        LaravelLog::info('Found matching Australian location', [
                            'location_name' => $row[1] ?? '',
                            'canonical_name' => $row[2] ?? '',
                            'target_type' => $row[5] ?? ''
                        ]);
                    }
                }
            }
            
            fclose($file);
            
            LaravelLog::info('Completed location search', [
                'total_processed' => $totalProcessed,
                'australian_locations_found' => $australianLocationsFound,
                'matching_results' => count($locations)
            ]);
            
            return $locations;
        } catch (Exception $e) {
            LaravelLog::error('Failed to search locations: ' . $e->getMessage(), [
                'query' => $query,
                'stack_trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Get the Custom Targeting Service
     *
     * @return \App\Services\CustomTargetingService
     * @throws Exception
     */
    public function getCustomTargetingService(): \App\Services\CustomTargetingService
    {
        try {
            // Ensure session is initialized
            if (!isset($this->session)) {
                LaravelLog::warning("AdManager session not initialized when requesting CustomTargetingService");
                
                // Try to initialize the session
                LaravelLog::info("Attempting to initialize AdManager session");
                
                try {
                    // Create session builder
                    $sessionBuilder = new AdManagerSessionBuilder();
                    
                    // Set the network code and application name
                    $sessionBuilder->withNetworkCode(self::NETWORK_CODE);
                    $sessionBuilder->withApplicationName(self::APPLICATION_NAME);
                    
                    // Load service account credentials
                    $jsonKeyPath = '/Users/mcnuser/Desktop/scripts/gam-api-test-339711-d8244e01556a.json';
                    LaravelLog::info("Loading service account from: {$jsonKeyPath}");
                    
                    if (!file_exists($jsonKeyPath)) {
                        throw new Exception("Service account JSON key file not found at: {$jsonKeyPath}");
                    }

                    $jsonKey = json_decode(file_get_contents($jsonKeyPath), true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new Exception("Failed to parse JSON key file: " . json_last_error_msg());
                    }
                    
                    // Create service account credentials
                    $oauth2Credential = new ServiceAccountCredentials(
                        'https://www.googleapis.com/auth/dfp',
                        $jsonKey
                    );
                    
                    $sessionBuilder->withOAuth2Credential($oauth2Credential);
                    
                    // Build the session
                    $this->session = $sessionBuilder->build();
                    
                    LaravelLog::info("Successfully initialized AdManager session");
                } catch (\Exception $e) {
                    LaravelLog::error("Failed to initialize AdManager session: " . $e->getMessage(), [
                        'exception' => $e,
                        'trace' => $e->getTraceAsString()
                    ]);
                    
                    // Try one more time with a different approach
                    LaravelLog::info("Attempting to initialize AdManager session with alternative method");
                    
                    try {
                        // Load and parse adsapi_php.ini
                        $iniPath = '/Users/mcnuser/Desktop/scripts/adsapi_php.ini';
                        LaravelLog::info("Loading adsapi_php.ini from: {$iniPath}");
                        
                        if (!file_exists($iniPath)) {
                            throw new Exception("adsapi_php.ini file not found at: {$iniPath}");
                        }
                        
                        $iniConfig = parse_ini_file($iniPath, true);
                        if ($iniConfig === false) {
                            throw new Exception("Failed to parse adsapi_php.ini file");
                        }
                        
                        // Override network code and application name
                        $iniConfig['AD_MANAGER']['networkCode'] = self::NETWORK_CODE;
                        $iniConfig['AD_MANAGER']['applicationName'] = self::APPLICATION_NAME;
                        
                        // Add SOAP logging configuration
                        $iniConfig['LOGGING']['soapLogFilePath'] = storage_path('logs/soap.log');
                        $iniConfig['LOGGING']['soapLogEnabled'] = 'true';
                        
                        // Create configuration
                        $config = new Configuration($iniConfig);
                        
                        // Create a new session builder with explicit OAuth2 credentials
                        $oAuth2Credential = (new OAuth2TokenBuilder())
                            ->fromFile(config('services.google_ad_manager.oauth_credentials_file', $jsonKeyPath))
                            ->build();
                        
                        $sessionBuilder = new AdManagerSessionBuilder();
                        $sessionBuilder->withOAuth2Credential($oAuth2Credential);
                        $sessionBuilder->withNetworkCode(self::NETWORK_CODE);
                        $sessionBuilder->withApplicationName(self::APPLICATION_NAME);
                        $sessionBuilder->from($config);
                        
                        // Build the session
                        $this->session = $sessionBuilder->build();
                        
                        LaravelLog::info("Successfully initialized AdManager session with alternative method");
                    } catch (\Exception $innerException) {
                        LaravelLog::error("Failed to initialize AdManager session with alternative method: " . $innerException->getMessage(), [
                            'exception' => $innerException,
                            'trace' => $innerException->getTraceAsString()
                        ]);
                        
                        // Re-throw the original exception
                        throw $e;
                    }
                }
            }
            
            // Verify session is initialized before creating service
            if (!isset($this->session)) {
                throw new Exception("Failed to initialize AdManager session after multiple attempts");
            }
            
            LaravelLog::info("Creating CustomTargetingService with session");
        return new CustomTargetingService($this->session);
        } catch (Exception $e) {
            LaravelLog::error("Error creating CustomTargetingService: " . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Get information about the current session
     *
     * @return array
     */
    public function getSessionInfo(): array
    {
        try {
            $info = [
                'session_initialized' => isset($this->session),
                'network_code' => self::NETWORK_CODE,
                'application_name' => self::APPLICATION_NAME
            ];
            
            if (isset($this->session)) {
                $info['session_type'] = get_class($this->session);
            }
            
            return $info;
        } catch (\Exception $e) {
            LaravelLog::error('Error getting session info: ' . $e->getMessage());
            return [
                'error' => $e->getMessage(),
                'session_initialized' => false
            ];
        }
    }

    /**
     * Get the AdManager session
     *
     * @return AdManagerSession
     */
    public function getSession(): AdManagerSession
    {
        if (!isset($this->session)) {
            LaravelLog::error('Session not initialized');
            throw new \Exception('Session not initialized');
        }
        
        return $this->session;
    }

    /**
     * Get the Custom Targeting Service directly from Google Ad Manager
     *
     * @return \Google\AdsApi\AdManager\v202411\CustomTargetingService
     */
    public function getGAMCustomTargetingService(): \Google\AdsApi\AdManager\v202411\CustomTargetingService
    {
        if ($this->customTargetingService === null) {
            try {
                LaravelLog::info("Creating Google Ad Manager CustomTargetingService");
                $this->customTargetingService = $this->serviceFactory->createCustomTargetingService($this->session);
                LaravelLog::info("Successfully created Google Ad Manager CustomTargetingService");
            } catch (\Exception $e) {
                LaravelLog::error("Failed to create Google Ad Manager CustomTargetingService: " . $e->getMessage(), [
                    'exception' => $e,
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        }
        return $this->customTargetingService;
    }

    /**
     * Search for custom targeting keys directly using the Google Ad Manager API
     *
     * @param string|null $query
     * @return array
     */
    public function searchCustomTargetingKeys(string $query = null): array
    {
        try {
            $service = $this->getGAMCustomTargetingService();
            $statement = new Statement();
            
            if ($query) {
                $statement->setQuery("WHERE name LIKE '%" . $query . "%' OR displayName LIKE '%" . $query . "%' ORDER BY name ASC LIMIT 20");
            } else {
                $statement->setQuery("ORDER BY name ASC LIMIT 20");
            }
            
            LaravelLog::info("Fetching custom targeting keys with query: " . ($query ? $query : "empty"));
            $response = $service->getCustomTargetingKeysByStatement($statement);
            
            if (!$response->getResults()) {
                LaravelLog::info("No custom targeting keys found");
                return [];
            }
            
            LaravelLog::info("Found " . count($response->getResults()) . " custom targeting keys");
            
            return array_map(function($key) {
                return [
                    'id' => $key->getId(),
                    'name' => $key->getName(),
                    'displayName' => $key->getDisplayName(),
                    'type' => $key->getType()
                ];
            }, $response->getResults());
        } catch (Exception $e) {
            LaravelLog::error('Failed to fetch custom targeting keys: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Search for custom targeting values for a specific key
     * 
     * @param int $keyId
     * @param string|null $query
     * @return array
     */
    public function searchCustomTargetingValues(int $keyId, string $query = null): array
    {
        try {
            $service = $this->getGAMCustomTargetingService();
            $statement = new Statement();
            
            $queryString = "WHERE customTargetingKeyId = " . $keyId;
            
            if ($query && !empty(trim($query))) {
                $queryString .= " AND name LIKE '%" . $query . "%'";
            }
            
            $queryString .= " ORDER BY name ASC LIMIT 20";
            $statement->setQuery($queryString);
            
            LaravelLog::info("Fetching custom targeting values for key ID: " . $keyId . " with query: " . ($query ? $query : "empty"));
            $response = $service->getCustomTargetingValuesByStatement($statement);
            
            if (!$response->getResults()) {
                LaravelLog::info("No custom targeting values found for key ID: " . $keyId);
                return [];
            }
            
            LaravelLog::info("Found " . count($response->getResults()) . " custom targeting values for key ID: " . $keyId);
            
            return array_map(function($value) {
                return [
                    'id' => $value->getId(),
                    'name' => $value->getName(),
                    'displayName' => $value->getDisplayName(),
                    'customTargetingKeyId' => $value->getCustomTargetingKeyId(),
                    'matchType' => $value->getMatchType()
                ];
            }, $response->getResults());
        } catch (Exception $e) {
            LaravelLog::error('Failed to fetch custom targeting values: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Get the audience segment service
     *
     * @return \Google\AdsApi\AdManager\v202411\AudienceSegmentService
     */
    public function getAudienceSegmentService()
    {
        return $this->serviceFactory->createAudienceSegmentService($this->session);
    }

    /**
     * Get the CMS metadata service
     *
     * @return \Google\AdsApi\AdManager\v202411\CmsMetadataService
     */
    public function getCmsMetadataService()
    {
        try {
            LaravelLog::info("Creating CMS Metadata Service");
            
            // Ensure session is initialized
            if (!isset($this->session)) {
                LaravelLog::error("AdManager session not initialized when requesting CmsMetadataService");
                throw new \Exception("AdManager session not initialized");
            }
            
            // Create the service
            $cmsMetadataService = $this->serviceFactory->createCmsMetadataService($this->session);
            
            LaravelLog::info("Successfully created CMS Metadata Service");
            return $cmsMetadataService;
        } catch (\Exception $e) {
            LaravelLog::error("Failed to create CMS Metadata Service: " . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function verifyLineItemUpdate(string $lineItemId, array $expectedUpdates): array
    {
        try {
            // Get the line item service
            $lineItemService = $this->serviceFactory->createLineItemService($this->session);

            // Create a statement to get the line item
            $statement = new Statement();
            $statement->setQuery("WHERE id = :lineItemId");
            $statement->setValues([
                'lineItemId' => new NumberValue(['value' => $lineItemId])
            ]);

            // Get the line item
            $page = $lineItemService->getLineItemsByStatement($statement);

            if ($page->getResults() === null || count($page->getResults()) === 0) {
                return [
                    'verified' => false,
                    'message' => 'Line item not found',
                    'updates' => []
                ];
            }

            $lineItem = $page->getResults()[0];
            $verifiedUpdates = [];
            $failedUpdates = [];

            // Check if line item is waiting for buyer acceptance
            $isProgrammatic = $lineItem->getLineItemType() === 'PROGRAMMATIC_GUARANTEED';
            $isAwaitingBuyer = $isProgrammatic && $lineItem->getStatus() === 'DRAFT';

            if ($isAwaitingBuyer) {
                return [
                    'verified' => true,
                    'message' => 'Line item is programmatic and awaiting buyer acceptance',
                    'updates' => [],
                    'awaiting_buyer' => true
                ];
            }

            // Log the current state of the line item
            LaravelLog::info('Current line item state during verification:', [
                'line_item_id' => $lineItem->getId(),
                'delivery_rate_type' => $lineItem->getDeliveryRateType(),
                'expected_updates' => $expectedUpdates
            ]);

            // Verify each updated field
            foreach ($expectedUpdates as $field => $expectedValue) {
                $currentValue = null;

                // Get current value based on field
                switch ($field) {
                    case 'priority':
                        $currentValue = $lineItem->getPriority();
                        break;
                    case 'status':
                        $currentValue = $lineItem->getStatus();
                        break;
                    case 'budget':
                        $budget = $lineItem->getBudget();
                        $currentValue = $budget ? $budget->getMicroAmount() / 1000000 : null;
                        break;
                    case 'delivery_rate_type':
                        $currentValue = $lineItem->getDeliveryRateType();
                        LaravelLog::info('Verifying delivery rate type', [
                            'line_item_id' => $lineItem->getId(),
                            'expected' => $expectedValue,
                            'actual' => $currentValue
                        ]);
                        break;
                    // Add more fields as needed
                }

                if ($currentValue !== null) {
                    if (strval($currentValue) === strval($expectedValue)) {
                        $verifiedUpdates[$field] = true;
                        LaravelLog::info("Field verified successfully", [
                            'field' => $field,
                            'expected' => $expectedValue,
                            'actual' => $currentValue
                        ]);
                    } else {
                        $failedUpdates[$field] = [
                            'expected' => $expectedValue,
                            'actual' => $currentValue
                        ];
                        LaravelLog::warning("Field verification failed", [
                            'field' => $field,
                            'expected' => $expectedValue,
                            'actual' => $currentValue,
                            'line_item_id' => $lineItem->getId()
                        ]);
                    }
                }
            }

            // Verify geo targeting changes
            if (isset($data['geo_targeting_included_add']) || isset($data['geo_targeting_excluded_add'])) {
                $targeting = $lineItem->getTargeting();
                $geoTargeting = $targeting ? $targeting->getGeoTargeting() : null;
                
                if ($geoTargeting) {
                    // Verify included locations
                    if (isset($data['geo_targeting_included_add'])) {
                        $expectedLocations = array_map('trim', explode(',', $data['geo_targeting_included_add']));
                        $actualLocations = array_map(function($loc) { 
                            return (string)$loc->getId(); 
                        }, $geoTargeting->getTargetedLocations() ?? []);
                        
                        $missingLocations = array_diff($expectedLocations, $actualLocations);
                        if (!empty($missingLocations)) {
                            $failedUpdates['geo_targeting_included'] = [
                                'expected' => $expectedLocations,
                                'actual' => $actualLocations,
                                'missing' => $missingLocations
                            ];
                        } else {
                            $verifiedUpdates['geo_targeting_included'] = true;
                        }
                    }

                    // Verify excluded locations
                    if (isset($data['geo_targeting_excluded_add'])) {
                        $expectedLocations = array_map('trim', explode(',', $data['geo_targeting_excluded_add']));
                        $actualLocations = array_map(function($loc) { 
                            return (string)$loc->getId(); 
                        }, $geoTargeting->getExcludedLocations() ?? []);
                        
                        $missingLocations = array_diff($expectedLocations, $actualLocations);
                        if (!empty($missingLocations)) {
                            $failedUpdates['geo_targeting_excluded'] = [
                                'expected' => $expectedLocations,
                                'actual' => $actualLocations,
                                'missing' => $missingLocations
                            ];
                        } else {
                            $verifiedUpdates['geo_targeting_excluded'] = true;
                        }
                    }
                }
            }

            return [
                'verified' => empty($failedUpdates),
                'message' => empty($failedUpdates) ? 'All updates verified' : 'Some updates failed verification',
                'updates' => [
                    'verified' => $verifiedUpdates,
                    'failed' => $failedUpdates
                ]
            ];
        } catch (Exception $e) {
            LaravelLog::error('Error during verification: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'verified' => false,
                'message' => 'Error verifying updates: ' . $e->getMessage(),
                'updates' => []
            ];
        }
    }

    public function getProposalLineItemService()
    {
        if (!isset($this->proposalLineItemService)) {
            $this->proposalLineItemService = $this->serviceFactory->createProposalLineItemService($this->session);
        }
        return $this->proposalLineItemService;
    }

    /**
     * Get proposal line item ID by name
     *
     * @param string $name
     * @return string|null
     */
    public function getProposalLineItemIdByName(string $name): ?string
    {
        try {
            // Create a PQL query to search for the proposal line item
            $statement = new Statement();
            $statement->setQuery("WHERE name = :name");
            
            // Create a proper ValueMapEntry for the name parameter
            $valueMapEntry = new ValueMapEntry();
            $valueMapEntry->setKey('name');
            $valueMapEntry->setValue(new TextValue(['value' => $name]));
            
            $statement->setValues([$valueMapEntry]);

            // Search for the proposal line item
            $result = $this->getProposalLineItemService()->getProposalLineItemsByStatement($statement);
            
            if ($result->getResults() !== null && count($result->getResults()) > 0) {
                $proposalLineItem = $result->getResults()[0];
                
                // Log the found item
                Log::info('Found proposal line item by name', [
                    'name' => $name,
                    'id' => $proposalLineItem->getId(),
                    'line_item_id' => $proposalLineItem->getLineItemId()
                ]);
                
                return $proposalLineItem->getLineItemId() ? (string) $proposalLineItem->getLineItemId() : null;
            }
            
            return null;
        } catch (Exception $e) {
            Log::error('Error fetching proposal line item by name', [
                'name' => $name,
                'error' => $e->getMessage()
            ]);
            throw new Exception("Failed to fetch proposal line item: " . $e->getMessage());
        }
    }
} 