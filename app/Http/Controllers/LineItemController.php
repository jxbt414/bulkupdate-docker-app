<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CsvUploadRequest;
use App\Jobs\ProcessBulkUpdate;
use App\Models\LineItem;
use App\Models\Log;
use App\Services\CsvService;
use App\Services\GoogleAdManagerService;
use App\Traits\LineItemLocking;
use Exception;
use Google\AdsApi\AdManager\v202411\Statement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log as LaravelLog;
use Illuminate\Support\Facades\Bus;
use App\Jobs\ProcessLineItemUpdate;
use App\Models\Rollback;
use Inertia\Inertia;
use App\Services\CustomTargetingService;
use App\Models\Log as ActivityLog;
use App\Services\AudienceSegmentService;
use App\Services\CmsMetadataService;

class LineItemController extends Controller
{
    use LineItemLocking;

    protected $googleAdManagerService;
    protected $csvService;
    protected $customTargetingService;
    protected $audienceSegmentService;
    protected $cmsMetadataService;

    public function __construct(
        GoogleAdManagerService $googleAdManagerService,
        CsvService $csvService,
        CustomTargetingService $customTargetingService,
        AudienceSegmentService $audienceSegmentService,
        CmsMetadataService $cmsMetadataService
    ) {
        $this->googleAdManagerService = $googleAdManagerService;
        $this->csvService = $csvService;
        $this->customTargetingService = $customTargetingService;
        $this->audienceSegmentService = $audienceSegmentService;
        $this->cmsMetadataService = $cmsMetadataService;
    }

    public function upload(CsvUploadRequest $request): JsonResponse
    {
        try {
            $file = $request->file('csv');
            $data = $this->csvService->validateAndParseCsv($file);

            // Get the first row's keys as headers
            $headers = empty($data) ? [] : array_keys($data[0]);

            return response()->json([
                'status' => 'success',
                'message' => 'CSV validated successfully',
                'data' => $data,
                'headers' => $headers
            ]);
        } catch (Exception $e) {
            LaravelLog::error('CSV upload error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Show the static update form
     *
     * @return \Inertia\Response
     */
    public function staticUpdate()
    {
        return Inertia::render('LineItems/StaticUpdate');
    }

    /**
     * Show the preview page
     *
     * @param Request $request
     * @return \Inertia\Response
     */
    public function preview(Request $request)
    {
        return Inertia::render('LineItems/Preview', [
            'sessionId' => $request->input('sessionId')
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'line_item_id' => 'required|string',
                'line_item_name' => 'nullable|string',
                'budget' => 'nullable|numeric',
                'priority' => 'nullable|integer',
                'impression_goals' => 'nullable|integer',
                'delivery_rate_type' => 'nullable|string|in:EVENLY,FRONTLOADED,AS_FAST_AS_POSSIBLE',
                'targeting' => 'nullable|array',
                'labels' => 'nullable|array'
            ]);

            LaravelLog::info('Starting line item update process', [
                'data' => $validated,
                'user' => Auth::user()
            ]);

            $userId = Auth::user()->id;
            LaravelLog::info('User ID retrieved', ['user_id' => $userId]);

            $lineItemId = $validated['line_item_id'];

            // Try to acquire the lock
            if (!$this->acquireLock($lineItemId, $userId)) {
                $lockHolder = $this->getLockHolder($lineItemId);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Line item is currently being updated by another user.',
                    'lock_holder' => $lockHolder
                ], 409);
            }

            LaravelLog::info('Lock acquired for line item', ['line_item_id' => $lineItemId]);

            try {
                LaravelLog::info('Calling adManagerService->updateLineItem');
                $this->googleAdManagerService->updateLineItem($validated, $userId);
                LaravelLog::info('Line item update completed successfully');

                // Log successful update
                Log::create([
                    'user_id' => $userId,
                    'action' => 'update',
                    'description' => "Successfully updated line item {$lineItemId}",
                    'line_item_id' => $lineItemId,
                    'status' => 'success',
                    'type' => 'success'  // Add type for consistency
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Line item updated successfully'
                ]);
            } catch (Exception $e) {
                // Log error with proper string formatting
                Log::create([
                    'user_id' => $userId,
                    'action' => 'update',
                    'description' => "Failed to update line item {$lineItemId}",
                    'line_item_id' => $lineItemId,
                    'status' => 'error',
                    'error_message' => $e->getMessage()
                ]);

                throw $e;
            } finally {
                // Always release the lock, even if an error occurred
                $this->releaseLock($lineItemId, $userId);
            }
        } catch (Exception $e) {
            LaravelLog::error('Line item update error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function bulkUpdate(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'line_items' => 'required|array',
                'line_items.*.line_item_id' => 'required|string',
                'line_items.*.line_item_name' => 'nullable|string',
                'line_items.*.priority' => 'nullable|string',
                'line_items.*.impression_goals' => 'nullable|string',
                'line_items.*.budget' => 'nullable|string',
                'line_items.*.targeting' => 'nullable|string',
                'line_items.*.labels' => 'nullable|array',
            ]);

            // Generate a batch ID for this update
            $batchId = uniqid('batch_', true);
            $userId = Auth::id();

            LaravelLog::info('Starting bulk update', [
                'batch_id' => $batchId,
                'user_id' => $userId,
                'line_item_count' => count($data['line_items'])
            ]);

            // Initialize progress tracking in cache
            $totalItems = count($data['line_items']);
            $progressData = [
                'batch_id' => $batchId,
                'total' => $totalItems,
                'completed' => 0,
                'failed' => 0,
                'in_progress' => 0,
                'status' => 'in_progress',
                'started_at' => now()->toIso8601String(),
                'completed_at' => null,
                'failed_items' => [],
                'successful_items' => []
            ];
            
            // Store initial progress in cache
            Cache::put("bulk_update_status_{$batchId}", $progressData, now()->addHours(24));

            // Keep track of successful and failed updates
            $successful = 0;
            $failed = 0;
            $failedItems = [];
            $successfulItems = [];

            // Process each line item
            foreach ($data['line_items'] as $index => $item) {
                try {
                    // Update progress - mark item as in progress
                    $progressData['in_progress'] = $index + 1;
                    Cache::put("bulk_update_status_{$batchId}", $progressData, now()->addHours(24));
                    
                    // Keep data as strings, just like the test command
                    LaravelLog::info('Processing line item update', [
                        'batch_id' => $batchId,
                        'user_id' => $userId,
                        'line_item_id' => $item['line_item_id'],
                        'data' => $item
                    ]);

                    // Update the line item
                    $updatedLineItem = $this->googleAdManagerService->updateLineItem($item, $userId);

                    // Verify the updates
                    $verificationResult = $this->googleAdManagerService->verifyLineItemUpdate($item['line_item_id'], $item);

                    // Track successful updates with verification details
                    $successful++;
                    
                    // Create a formatted list of updated fields
                    $updatedFields = [];
                    foreach ($item as $field => $value) {
                        if ($value !== null && $value !== '') {
                            switch ($field) {
                                case 'status':
                                    $updatedFields[] = 'Status: ' . $value;
                                    break;
                                case 'priority':
                                    $updatedFields[] = 'Priority: ' . $value;
                                    break;
                                case 'budget':
                                    $updatedFields[] = 'Budget: ' . $value;
                                    break;
                                case 'impression_goals':
                                    $updatedFields[] = 'Impression Goals: ' . $value;
                                    break;
                                case 'labels':
                                    if (is_array($value) && !empty($value)) {
                                        $updatedFields[] = 'Labels: ' . count($value) . ' labels updated';
                                    }
                                    break;
                                case 'custom_targeting':
                                    if (!empty($value)) {
                                        $updatedFields[] = 'Custom Targeting: Updated';
                                    }
                                    break;
                                case 'device_category_targeting':
                                    if (!empty($value)) {
                                        $updatedFields[] = 'Device Categories: Updated';
                                    }
                                    break;
                                case 'day_part_targeting':
                                    if (!empty($value)) {
                                        $updatedFields[] = 'Day Parts: Updated';
                                    }
                                    break;
                                case 'geo_targeting_included':
                                    if (!empty($value)) {
                                        $updatedFields[] = 'Included Locations: Updated';
                                    }
                                    break;
                                case 'geo_targeting_excluded':
                                    if (!empty($value)) {
                                        $updatedFields[] = 'Excluded Locations: Updated';
                                    }
                                    break;
                                case 'inventory_targeting_included':
                                    if (!empty($value)) {
                                        $updatedFields[] = 'Included Inventory: Updated';
                                    }
                                    break;
                                case 'inventory_targeting_excluded':
                                    if (!empty($value)) {
                                        $updatedFields[] = 'Excluded Inventory: Updated';
                                    }
                                    break;
                            }
                        }
                    }

                    $successfulItems[] = [
                        'line_item_id' => $item['line_item_id'],
                        'line_item_name' => $item['line_item_name'] ?? $item['line_item_id'],
                        'verification' => $verificationResult,
                        'updated_fields' => implode(', ', $updatedFields)
                    ];

                    // Update progress data
                    $progressData['completed']++;
                    $progressData['successful_items'] = $successfulItems;

                    // If verification failed but update didn't throw an error, log it
                    if (!$verificationResult['verified'] && !isset($verificationResult['awaiting_buyer'])) {
                        LaravelLog::warning('Line item update verification failed', [
                            'batch_id' => $batchId,
                            'line_item_id' => $item['line_item_id'],
                            'verification_result' => $verificationResult,
                            'updated_fields' => $successfulItems[count($successfulItems) - 1]['updated_fields']
                        ]);
                    }

                    LaravelLog::info('Line item updated successfully', [
                        'batch_id' => $batchId,
                        'user_id' => $userId,
                        'line_item_id' => $item['line_item_id']
                    ]);
                } catch (Exception $e) {
                    // Log the error
                    LaravelLog::error('Failed to update line item', [
                        'batch_id' => $batchId,
                        'user_id' => $userId,
                        'line_item_id' => $item['line_item_id'],
                        'error' => $e->getMessage(),
                        'item_data' => $item
                    ]);

                    $failed++;
                    $failedItems[] = [
                        'line_item_id' => $item['line_item_id'],
                        'error' => $e->getMessage(),
                        'data' => $item
                    ];
                    
                    // Update progress - mark item as failed
                    $progressData['failed'] = $failed;
                    $progressData['failed_items'] = $failedItems;
                    Cache::put("bulk_update_status_{$batchId}", $progressData, now()->addHours(24));
                }
            }
            
            // Update final status
            $progressData['status'] = 'completed';
            $progressData['completed_at'] = now()->toIso8601String();
            Cache::put("bulk_update_status_{$batchId}", $progressData, now()->addHours(24));

            // Create only a summary log entry with all line item details
            Log::create([
                'user_id' => $userId,
                'batch_id' => $batchId,
                'action' => 'update',
                'status' => $successful > 0 ? 'success' : 'error',
                'description' => "Completed bulk update: {$successful} successful, {$failed} failed.",
                'data' => [
                    'successful' => $successful,
                    'failed' => $failed,
                    'successful_items' => array_map(function($item) {
                        return [
                            'line_item_id' => $item['line_item_id'],
                            'line_item_name' => $item['line_item_name'],
                            'verification' => $item['verification'],
                            'updated_fields' => $item['updated_fields'],
                            'awaiting_buyer' => $item['verification']['awaiting_buyer'] ?? false,
                            'verified_updates' => $item['verification']['updates']['verified'] ?? [],
                            'failed_updates' => $item['verification']['updates']['failed'] ?? []
                        ];
                    }, $successfulItems),
                    'failed_items' => $failedItems
                ]
            ]);

            // Return the results
            return response()->json([
                'status' => 'success',
                'message' => "Bulk update completed. {$successful} successful, {$failed} failed.",
                'results' => [
                    'successful' => $successful,
                    'failed' => $failed,
                    'failed_items' => $failedItems,
                    'batch_id' => $batchId
                ]
            ]);
        } catch (Exception $e) {
            LaravelLog::error('Bulk update failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process bulk update: ' . $e->getMessage()
            ], 500);
        }
    }

    public function rollback(string $lineItemId): JsonResponse
    {
        try {
            // Check if the last action for this line item was a rollback
            $lastLog = Log::where('line_item_id', $lineItemId)
                ->orderBy('created_at', 'desc')
                ->first();

            if ($lastLog && $lastLog->action === 'rollback') {
                throw new Exception('Cannot rollback a rollback operation');
            }

            $this->googleAdManagerService->rollback($lineItemId, Auth::id());
            
            return response()->json([
                'status' => 'success',
                'message' => "Successfully rolled back line item {$lineItemId}"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function rollbackBatch(string $batchId): JsonResponse
    {
        try {
            // Check if the last action for this batch was a rollback
            $lastLog = Log::where('batch_id', $batchId)
                ->orderBy('created_at', 'desc')
                ->first();

            if ($lastLog && $lastLog->action === 'rollback') {
                throw new Exception('Cannot rollback a rollback operation');
            }

            // Get all line items from this batch that were successfully updated
            $batchLog = Log::where('batch_id', $batchId)
                ->where('action', 'update')
                ->whereNull('line_item_id')  // Get the summary log
                ->first();

            if (!$batchLog || !$batchLog->data || empty($batchLog->data['successful_items'])) {
                throw new Exception('No successful updates found for this batch');
            }

            $userId = Auth::id();
            $successCount = 0;
            $failedCount = 0;
            $errors = [];
            $individualLogs = [];

            // Rollback each line item in the batch
            foreach ($batchLog->data['successful_items'] as $item) {
                try {
                    // Get the rollback data before performing the rollback
                    $rollback = Rollback::where('line_item_id', $item['line_item_id'])
                        ->latest('rollback_timestamp')
                        ->first();
                    
                    $this->googleAdManagerService->rollback($item['line_item_id'], $userId);
                    $successCount++;

                    // Store successful rollback log data with both previous and current values
                    $individualLogs[] = [
                        'user_id' => $userId,
                        'action' => 'rollback',
                        'description' => "Rolled back line item {$item['line_item_id']} from batch {$batchId}",
                        'line_item_id' => $item['line_item_id'],
                        'batch_id' => $batchId,
                        'status' => 'success',
                        'type' => 'rollback',
                        'data' => [
                            'previous_values' => $rollback ? $rollback->current_data : null,
                            'current_values' => $rollback ? $rollback->previous_data : null
                        ]
                    ];
                } catch (Exception $e) {
                    $failedCount++;
                    $errors[] = "Failed to rollback line item {$item['line_item_id']}: {$e->getMessage()}";
                    
                    // Store error log data
                    $individualLogs[] = [
                        'user_id' => $userId,
                        'action' => 'rollback',
                        'description' => "Failed to rollback line item {$item['line_item_id']} from batch {$batchId}",
                        'line_item_id' => $item['line_item_id'],
                        'batch_id' => $batchId,
                        'status' => 'error',
                        'type' => 'error',
                        'error_message' => $e->getMessage()
                    ];
                }
            }

            // Create summary log first
            Log::create([
                'user_id' => $userId,
                'action' => 'rollback',
                'description' => "Batch rollback completed: {$successCount} successful, {$failedCount} failed",
                'batch_id' => $batchId,
                'status' => $failedCount === 0 ? 'success' : 'partial',
                'type' => $failedCount === 0 ? 'success' : 'error',
                'data' => [
                    'successful' => $successCount,
                    'failed' => $failedCount,
                    'successful_items' => array_filter($individualLogs, function($log) {
                        return $log['status'] === 'success';
                    }),
                    'failed_items' => array_filter($individualLogs, function($log) {
                        return $log['status'] === 'error';
                    })
                ]
            ]);

            // Create individual logs after summary
            foreach ($individualLogs as $logData) {
                Log::create($logData);
            }

            return response()->json([
                'status' => 'success',
                'message' => "Batch rollback completed: {$successCount} successful, {$failedCount} failed",
                'errors' => $errors
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getLogs(): JsonResponse
    {
        try {
            $logs = Log::with('user')
                ->where('created_at', '>=', now()->subMonths(3))
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($log) {
                    // Ensure data is properly serialized
                    $data = $log->data;
                    
                    // Debug the data field
                    \Illuminate\Support\Facades\Log::debug('Log data for ID ' . $log->id, [
                        'data' => $data,
                        'data_type' => gettype($data),
                        'data_json' => json_encode($data)
                    ]);
                    
                    return [
                        'id' => $log->id,
                        'type' => $log->status === 'success' ? 'success' : 'error',
                        'message' => $log->description,
                        'line_item_id' => $log->line_item_id,
                        'batch_id' => $log->batch_id,
                        'action' => $log->action,
                        'created_at' => $log->created_at,
                        'data' => $data,
                        'error_message' => $log->error_message,
                        'user' => $log->user ? [
                            'name' => $log->user->name,
                            'email' => $log->user->email
                        ] : null
                    ];
                });

            return response()->json([
                'status' => 'success',
                'logs' => $logs
            ]);
        } catch (Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to fetch logs: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch logs'
            ], 500);
        }
    }

    public function mapFields(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'mappings' => 'required|array',
                'data' => 'required|array'
            ]);

            // Store the mapped data in the session for preview
            $mappedData = [];
            foreach ($validated['data'] as $row) {
                $mappedRow = [];
                foreach ($validated['mappings'] as $csvHeader => $fieldName) {
                    if (isset($row[$csvHeader]) && !empty($fieldName)) {
                        // Keep values as strings, similar to the test command
                        $mappedRow[$fieldName] = $row[$csvHeader];
                    }
                }
                
                // Skip rows that don't have required fields
                if (!isset($mappedRow['line_item_id'])) {
                    continue;
                }
                
                $mappedData[] = $mappedRow;
            }

            // Log the mapped data for debugging
            LaravelLog::info('Mapped data for preview', [
                'count' => count($mappedData),
                'sample' => array_slice($mappedData, 0, 2)
            ]);

            // Generate a unique ID for this mapping session
            $sessionId = uniqid('map_', true);
            
            // Store mapped data in cache for 1 hour
            Cache::put("mapped_data_{$sessionId}", $mappedData, now()->addHour());

            // Log successful mapping
            Log::create([
                'user_id' => Auth::user()->id,
                'action' => 'map_fields',
                'description' => 'Successfully mapped CSV fields',
                'status' => 'success'
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Fields mapped successfully',
                'id' => $sessionId,
                'preview' => array_slice($mappedData, 0, 5) // Return first 5 rows for preview
            ]);
        } catch (Exception $e) {
            LaravelLog::error('Field mapping error: ' . $e->getMessage());
            
            // Log error
            Log::create([
                'user_id' => Auth::user()->id,
                'action' => 'map_fields',
                'description' => 'Failed to map CSV fields',
                'status' => 'error',
                'error_message' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function getPreviewData(string $sessionId): JsonResponse
    {
        try {
            $mappedData = Cache::get("mapped_data_{$sessionId}");
            
            if (!$mappedData) {
                throw new Exception('Preview data not found or has expired');
            }

            // Get line item IDs
            $lineItemIds = array_column($mappedData, 'line_item_id');

            // Fetch current values from Google Ad Manager
            $statement = new Statement();
            $statement->setQuery("WHERE ID IN (" . implode(',', $lineItemIds) . ")");
            $response = $this->googleAdManagerService->getLineItemService()->getLineItemsByStatement($statement);
            
            // Convert LineItemPage results to an associative array by line item ID
            $currentValues = [];
            if ($response->getResults()) {
                foreach ($response->getResults() as $lineItem) {
                    $currentValues[$lineItem->getId()] = $lineItem;
                }
            }

            // Merge current and new values
            $mergedData = array_map(function ($item) use ($currentValues) {
                $currentItem = $currentValues[$item['line_item_id']] ?? null;
                
                if ($currentItem) {
                    return array_merge($item, [
                        'original_name' => $currentItem->getName(),
                        'original_type' => $currentItem->getLineItemType(),
                        'original_priority' => $currentItem->getPriority(),
                        'original_budget' => $currentItem->getBudget()?->getMicroAmount() / 1000000,
                        'original_impression_goals' => $currentItem->getPrimaryGoal()?->getUnits(),
                        'original_targeting' => $currentItem->getTargeting(),
                        'original_labels' => [] // Temporarily removing label handling until we can properly fetch label names
                    ]);
                }
                
                return $item;
            }, $mappedData);

            return response()->json([
                'status' => 'success',
                'data' => $mergedData
            ]);
        } catch (Exception $e) {
            LaravelLog::error('Failed to retrieve preview data: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 404);
        }
    }

    public function getBulkUpdateStatus(string $batchId): JsonResponse
    {
        try {
            $status = Cache::get("bulk_update_status_{$batchId}");
            
            if (!$status) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Batch status not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $status
            ]);
        } catch (Exception $e) {
            LaravelLog::error('Failed to get batch status: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function retryBulkUpdate(string $batchId): JsonResponse
    {
        try {
            $status = Cache::get("bulk_update_status_{$batchId}");
            
            if (!$status) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Batch status not found'
                ], 404);
            }

            if (empty($status['failed_items'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No failed items to retry'
                ], 400);
            }

            $userId = Auth::id();
            $failedItems = $status['failed_items'];

            // Create a new batch for failed items
            $newBatchId = 'retry_' . $batchId;
            
            // Instead of using ProcessBulkUpdate job, process items directly
            $successful = 0;
            $failed = 0;
            $newFailedItems = [];
            
            foreach ($failedItems as $item) {
                try {
                    $this->googleAdManagerService->updateLineItem($item['data'], $userId);
                    $successful++;
                } catch (Exception $e) {
                    $failed++;
                    $newFailedItems[] = [
                        'line_item_id' => $item['data']['line_item_id'],
                        'error' => $e->getMessage(),
                        'data' => $item['data']
                    ];
                }
            }

            // Update original batch status
            $status['retry_batch_id'] = $newBatchId;
            $status['retry_results'] = [
                'successful' => $successful,
                'failed' => $failed,
                'failed_items' => $newFailedItems
            ];
            Cache::put("bulk_update_status_{$batchId}", $status, now()->addDay());

            // Log retry attempt
            Log::create([
                'user_id' => $userId,
                'action' => 'bulk_update_retry',
                'description' => "Started retry batch {$newBatchId} for {$batchId} with " . count($failedItems) . " items",
                'status' => 'success'
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Retry batch completed',
                'retry_batch_id' => $newBatchId,
                'results' => [
                    'successful' => $successful,
                    'failed' => $failed
                ]
            ]);
        } catch (Exception $e) {
            LaravelLog::error('Failed to retry bulk update: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function downloadStaticSampleCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="static-update-sample.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $callback = function() {
            $file = fopen('php://output', 'w');
            
            // Write headers
            fputcsv($file, [
                'line_item_id',
                'line_item_name'
            ]);

            // Write sample data
            fputcsv($file, [
                '123456789',
                'Sample Line Item'
            ]);

            // Write another sample row
            fputcsv($file, [
                '987654321',
                'Another Line Item'
            ]);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function downloadDynamicSampleCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="dynamic_sample.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $callback = function() {
            $file = fopen('php://output', 'w');
            
            // Write headers
            fputcsv($file, [
                'line_item_id',
                'line_item_name',
                'budget',
                'line_item_type',
                'priority',
                'impression_goals',
                'status',
                'start_date_time',
                'end_date_time',
                'unlimited_end_date',
                'delivery_rate_type',
                'cost_type',
                'cost_per_unit',
                'frequency_cap_max',
                'frequency_cap_time_units',
                'frequency_cap_time_unit_type',
                'frequency_cap_remove',
                'geo_targeting_included_add',
                'geo_targeting_included_remove',
                'geo_targeting_excluded_add',
                'geo_targeting_excluded_remove',
                'inventory_targeting_included_add',
                'inventory_targeting_included_remove',
                'inventory_targeting_excluded_add',
                'inventory_targeting_excluded_remove',
                'custom_targeting_add',
                'custom_targeting_remove',
                'custom_targeting_key_remove',
                'day_part_targeting_add',
                'day_part_targeting_remove',
                'device_category_targeting_add',
                'device_category_targeting_remove',
                'labels_add',
                'labels_remove'
            ]);

            // Example 1: Standard line item with targeting modifications
            fputcsv($file, [
                '6342804872',                                    // line_item_id
                'Standard Line Item - Modified Targeting',        // line_item_name
                '5000.50',                                       // budget
                'STANDARD',                                      // line_item_type
                '8',                                            // priority
                '1000000',                                      // impression_goals
                'PAUSE',                                        // status
                '2024-03-20 00:00:00',                         // start_date_time
                '2024-12-31 23:59:59',                         // end_date_time
                'false',                                        // unlimited_end_date
                'EVENLY',                                       // delivery_rate_type
                'CPM',                                          // cost_type
                '2.50',                                         // cost_per_unit
                '5',                                            // frequency_cap_max
                '24',                                           // frequency_cap_time_units
                'HOUR',                                         // frequency_cap_time_unit_type
                'HOUR_5',                                       // frequency_cap_remove
                'NSW,VIC',                                      // geo_targeting_included_add
                'QLD',                                          // geo_targeting_included_remove
                'WA',                                           // geo_targeting_excluded_add
                'SA',                                           // geo_targeting_excluded_remove
                'ad_unit_1,ad_unit_2',                         // inventory_targeting_included_add
                'ad_unit_3',                                    // inventory_targeting_included_remove
                'ad_unit_4',                                    // inventory_targeting_excluded_add
                'ad_unit_5',                                    // inventory_targeting_excluded_remove
                'gender=male,age=25-34',                       // custom_targeting_add
                'interest=sports',                             // custom_targeting_remove
                'gender',                                       // custom_targeting_key_remove
                'MONDAY_TO_FRIDAY_9_TO_5',                     // day_part_targeting_add
                'WEEKENDS',                                    // day_part_targeting_remove
                'DESKTOP,MOBILE',                              // device_category_targeting_add
                'CONNECTED_TV',                                // device_category_targeting_remove
                'premium,high-priority',                       // labels_add
                'old-label,deprecated'                         // labels_remove
            ]);

            // Example 2: Sponsorship line item with targeting changes
            fputcsv($file, [
                '6342804873',                                    // line_item_id
                'Sponsorship Line Item - Targeting Update',       // line_item_name
                '10000.00',                                      // budget
                'SPONSORSHIP',                                   // line_item_type
                '2',                                            // priority
                '80',                                           // impression_goals (percentage)
                'ARCHIVE',                                      // status
                '2024-04-01 00:00:00',                         // start_date_time
                '2024-12-31 23:59:59',                         // end_date_time
                'false',                                        // unlimited_end_date
                'FRONTLOADED',                                  // delivery_rate_type
                'CPM',                                          // cost_type
                '5.00',                                         // cost_per_unit
                '10',                                           // frequency_cap_max
                '1',                                            // frequency_cap_time_units
                'DAY',                                          // frequency_cap_time_unit_type
                'DAY_10',                                       // frequency_cap_remove
                '2000,2010,2020',                              // geo_targeting_included_add (postcodes)
                '2030,2040',                                    // geo_targeting_included_remove
                '3000',                                         // geo_targeting_excluded_add
                '3010',                                         // geo_targeting_excluded_remove
                'placement_1,placement_2',                      // inventory_targeting_included_add
                'placement_3',                                  // inventory_targeting_included_remove
                'placement_4',                                  // inventory_targeting_excluded_add
                'placement_5',                                  // inventory_targeting_excluded_remove
                'device=iphone,os=ios',                        // custom_targeting_add
                'browser=chrome',                              // custom_targeting_remove
                'device',                                       // custom_targeting_key_remove
                'BUSINESS_HOURS',                              // day_part_targeting_add
                'AFTER_HOURS',                                 // day_part_targeting_remove
                'TABLET',                                      // device_category_targeting_add
                'MOBILE',                                      // device_category_targeting_remove
                'premium,sponsorship',                         // labels_add
                'test-label,draft'                            // labels_remove
            ]);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function getAvailableLabels(Request $request): JsonResponse
    {
        try {
            // Get label service from GAM
            $labelService = $this->googleAdManagerService->getLabelService();
            
            // Create a statement to fetch competitive exclusion labels
            $statement = new Statement();
            
            // Add search condition if search parameter is provided
            if ($request->has('search')) {
                $search = $request->get('search');
                $statement->setQuery("WHERE type = 'COMPETITIVE_EXCLUSION' AND name LIKE '%" . $search . "%' ORDER BY name ASC LIMIT 20");
            } else {
                $statement->setQuery("WHERE type = 'COMPETITIVE_EXCLUSION' ORDER BY name ASC LIMIT 20");
            }
            
            // Fetch labels from GAM
            $response = $labelService->getLabelsByStatement($statement);
            
            if (!$response->getResults()) {
                return response()->json([
                    'status' => 'success',
                    'labels' => []
                ]);
            }

            // Transform labels into a format suitable for frontend
            $labels = array_map(function($label) {
                return [
                    'id' => $label->getId(),
                    'name' => $label->getName(),
                    'description' => $label->getDescription(),
                    'types' => $label->getTypes() // Array of label types
                ];
            }, $response->getResults());

            return response()->json([
                'status' => 'success',
                'labels' => $labels
            ]);
        } catch (Exception $e) {
            LaravelLog::error('Failed to fetch labels: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getAvailableAdUnits(Request $request): JsonResponse
    {
        try {
            $search = $request->input('search', '');
            $adUnits = $this->googleAdManagerService->searchAdUnits($search);
            
            return response()->json([
                'status' => 'success',
                'adUnits' => $adUnits
            ]);
        } catch (\Exception $e) {
            LaravelLog::error('Failed to get available ad units: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get available ad units: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getAvailablePlacements(Request $request): JsonResponse
    {
        try {
            $search = $request->get('search');
            $placements = $this->googleAdManagerService->searchPlacements($search);
            
            return response()->json([
                'status' => 'success',
                'placements' => $placements
            ]);
        } catch (Exception $e) {
            LaravelLog::error('Failed to fetch placements: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available custom targeting keys based on search query
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCustomTargetingKeys(Request $request)
    {
        try {
            $search = $request->query('search', '');
            
            \Illuminate\Support\Facades\Log::info('Received request for custom targeting keys', [
                'search' => $search,
                'user_id' => \Illuminate\Support\Facades\Auth::id()
            ]);
            
            // Get custom targeting keys directly from Google Ad Manager
            \Illuminate\Support\Facades\Log::info('Searching for custom targeting keys directly');
            $keys = $this->googleAdManagerService->searchCustomTargetingKeys($search);
            \Illuminate\Support\Facades\Log::info('Successfully fetched custom targeting keys', [
                'count' => count($keys)
            ]);
            
            // Transform the keys to a format suitable for the frontend
            $transformedKeys = collect($keys)->map(function($key) {
                return [
                    'id' => $key['id'],
                    'name' => $key['name'],
                    'displayName' => $key['displayName'] ?? $key['name']
                ];
            })->toArray();
            
            \Illuminate\Support\Facades\Log::info('Transformed keys for frontend', [
                'transformed_count' => count($transformedKeys)
            ]);
            
            return response()->json($transformedKeys);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to fetch custom targeting keys: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'search' => $request->query('search', '')
            ]);
            
            return response()->json([
                'error' => 'Failed to fetch custom targeting keys',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available custom targeting values for a specific key based on search query
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCustomTargetingValues(Request $request)
    {
        try {
            $keyId = $request->query('key_id');
            $keyName = $request->query('key');
            $search = $request->query('search', '');
            
            if (!$keyId && !$keyName) {
                return response()->json([
                    'error' => 'Either key_id or key name is required',
                    'status' => 'error'
                ], 400);
            }
            
            \Illuminate\Support\Facades\Log::info('Received request for custom targeting values', [
                'key_id' => $keyId,
                'key' => $keyName,
                'search' => $search,
                'user_id' => \Illuminate\Support\Facades\Auth::id()
            ]);
            
            // If we have a key ID, use it directly
            if ($keyId) {
                \Illuminate\Support\Facades\Log::info('Using provided key ID: ' . $keyId);
                
                // Get values for the key
                \Illuminate\Support\Facades\Log::info('Searching for custom targeting values for key ID: ' . $keyId);
                $values = $this->googleAdManagerService->searchCustomTargetingValues((int)$keyId, $search);
                \Illuminate\Support\Facades\Log::info('Successfully fetched custom targeting values', [
                    'count' => count($values)
                ]);
            } else {
                // First, find the key ID by name
                \Illuminate\Support\Facades\Log::info('Searching for custom targeting key by name: ' . $keyName);
                $keys = $this->googleAdManagerService->searchCustomTargetingKeys($keyName);
                
                if (empty($keys)) {
                    \Illuminate\Support\Facades\Log::warning('No custom targeting key found with name: ' . $keyName);
                    return response()->json([
                        'status' => 'success',
                        'values' => []
                    ]);
                }
                
                // Find the exact key match
                $key = null;
                foreach ($keys as $k) {
                    if ($k['name'] === $keyName) {
                        $key = $k;
                        break;
                    }
                }
                
                if (!$key) {
                    \Illuminate\Support\Facades\Log::warning('No exact match found for custom targeting key: ' . $keyName);
                    return response()->json([
                        'status' => 'success',
                        'values' => []
                    ]);
                }
                
                \Illuminate\Support\Facades\Log::info('Found custom targeting key', [
                    'key_id' => $key['id'],
                    'key_name' => $key['name']
                ]);
                
                // Get values for the key
                \Illuminate\Support\Facades\Log::info('Searching for custom targeting values for key ID: ' . $key['id']);
                $values = $this->googleAdManagerService->searchCustomTargetingValues($key['id'], $search);
                \Illuminate\Support\Facades\Log::info('Successfully fetched custom targeting values', [
                    'count' => count($values)
                ]);
            }
            
            // Transform the values to a format suitable for the frontend
            $transformedValues = collect($values)->map(function($value) {
                return [
                    'id' => $value['id'],
                    'name' => $value['name'],
                    'displayName' => $value['displayName'] ?? $value['name']
                ];
            })->toArray();
            
            \Illuminate\Support\Facades\Log::info('Transformed values for frontend', [
                'transformed_count' => count($transformedValues)
            ]);
            
            return response()->json([
                'status' => 'success',
                'values' => $transformedValues
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to fetch custom targeting values: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'key_id' => $request->query('key_id'),
                'key' => $request->query('key'),
                'search' => $request->query('search', '')
            ]);
            
            return response()->json([
                'status' => 'error',
                'error' => 'Failed to fetch custom targeting values',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test endpoint for custom targeting keys
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function testCustomTargetingKeys()
    {
        try {
            // Get session info
            $sessionInfo = $this->googleAdManagerService->getSessionInfo();
            
            // Log the session info
            \Illuminate\Support\Facades\Log::info('Testing custom targeting keys - Session info', $sessionInfo);
            
            // Get debug info from the custom targeting service
            $debugInfo = $this->customTargetingService->debug();
            \Illuminate\Support\Facades\Log::info('Custom targeting service debug info', $debugInfo);
            
            // Try to fetch a few custom targeting keys with a simple query
            $customTargetingKeys = [];
            $keysError = null;
            
            try {
                $customTargetingKeys = $this->customTargetingService->getCustomTargetingKeys('a');
                
                // Log the result
                if (!empty($customTargetingKeys)) {
                    \Illuminate\Support\Facades\Log::info('Successfully fetched custom targeting keys', [
                        'count' => count($customTargetingKeys),
                        'first_few' => array_slice($customTargetingKeys, 0, 5)
                    ]);
                } else {
                    \Illuminate\Support\Facades\Log::warning('No custom targeting keys found');
                }
            } catch (\Exception $e) {
                $keysError = $e->getMessage();
                \Illuminate\Support\Facades\Log::error('Error fetching custom targeting keys: ' . $e->getMessage(), [
                    'exception' => $e,
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            // Return the session info, debug info, and keys for debugging
            return response()->json([
                'status' => 'success',
                'message' => 'Test completed successfully',
                'session_info' => $sessionInfo,
                'debug_info' => $debugInfo,
                'keys' => $customTargetingKeys,
                'keys_error' => $keysError
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error testing custom targeting keys: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error testing custom targeting keys: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Get available locations based on search query
     */
    public function getAvailableLocations(Request $request)
    {
        try {
            $search = $request->input('search', '');
            $locations = $this->googleAdManagerService->searchLocations($search);
            
            return response()->json([
                'status' => 'success',
                'locations' => $locations
            ]);
        } catch (\Exception $e) {
            LaravelLog::error('Failed to get available locations: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get available locations: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get audience segments based on search query
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getAudienceSegments(Request $request): JsonResponse
    {
        try {
            $query = $request->query('search', '');
            
            \Illuminate\Support\Facades\Log::info('Fetching audience segments', [
                'search_query' => $query
            ]);
            
            $segments = $this->audienceSegmentService->searchSegments($query);
            
            \Illuminate\Support\Facades\Log::info('Successfully fetched audience segments', [
                'count' => count($segments),
                'sample' => !empty($segments) ? $segments[0] : null
            ]);
            
            return response()->json([
                'status' => 'success',
                'segments' => $segments
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to fetch audience segments: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'search' => $request->query('search', '')
            ]);
            
            return response()->json([
                'status' => 'error',
                'error' => 'Failed to fetch audience segments',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get CMS metadata keys based on search query
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getCmsMetadataKeys(Request $request): JsonResponse
    {
        try {
            $query = $request->query('search', '');
            
            \Illuminate\Support\Facades\Log::info('Fetching CMS metadata keys', [
                'search_query' => $query
            ]);
            
            $keys = $this->cmsMetadataService->searchMetadata($query);
            
            \Illuminate\Support\Facades\Log::info('Successfully fetched CMS metadata keys', [
                'count' => count($keys),
                'sample' => !empty($keys) ? $keys[0] : null
            ]);
            
            return response()->json([
                'status' => 'success',
                'keys' => $keys
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to fetch CMS metadata keys: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'search' => $request->query('search', '')
            ]);
            
            return response()->json([
                'status' => 'error',
                'error' => 'Failed to fetch CMS metadata keys',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get CMS metadata values for a specific key
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getCmsMetadataValues(Request $request): JsonResponse
    {
        try {
            $keyId = $request->query('key_id');
            $query = $request->query('search', '');
            
            if (!$keyId) {
                throw new \InvalidArgumentException('key_id is required');
            }
            
            LaravelLog::info('Fetching CMS metadata values', [
                'keyId' => $keyId,
                'query' => $query
            ]);
            
            $values = $this->cmsMetadataService->searchMetadataValues($keyId, $query);
            
            LaravelLog::info('Successfully fetched CMS metadata values', [
                'count' => count($values),
                'sample' => isset($values[0]) ? $values[0] : null
            ]);
            
            return response()->json([
                'status' => 'success',
                'values' => $values
            ]);
        } catch (\Exception $e) {
            LaravelLog::error('Failed to fetch CMS metadata values: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'key_id' => $request->query('key_id'),
                'search' => $request->query('search', '')
            ]);
            
            return response()->json([
                'status' => 'error',
                'error' => 'Failed to fetch CMS metadata values',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test CMS metadata service directly
     *
     * @return JsonResponse
     */
    public function testCmsMetadata(): JsonResponse
    {
        try {
            // Log service information
            Log::info('CMS Metadata Service Test', [
                'service_class' => get_class($this->cmsMetadataService),
                'session_info' => $this->googleAdManagerService->getSessionInfo()
            ]);
            
            // Try to get CMS metadata with an empty query
            $metadata = $this->cmsMetadataService->searchMetadata('');
            
            return response()->json([
                'status' => 'success',
                'message' => 'CMS metadata service test completed successfully',
                'service_class' => get_class($this->cmsMetadataService),
                'metadata_count' => count($metadata),
                'metadata_sample' => !empty($metadata) ? $metadata[0] : null,
                'session_info' => $this->googleAdManagerService->getSessionInfo()
            ]);
        } catch (\Exception $e) {
            Log::error('CMS Metadata Service Test Failed', [
                'exception' => $e,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'CMS metadata service test failed: ' . $e->getMessage(),
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Test endpoint for verifying line item updates
     */
    public function testVerifyUpdate(string $lineItemId): JsonResponse
    {
        try {
            // Create a test update with some fields
            $testUpdate = [
                'status' => 'PAUSED',
                'priority' => '10',
                'budget' => '1000'
            ];

            // Try to verify the line item
            $verificationResult = $this->googleAdManagerService->verifyLineItemUpdate($lineItemId, $testUpdate);

            return response()->json([
                'status' => 'success',
                'message' => 'Verification test completed',
                'result' => $verificationResult
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Verification test failed: ' . $e->getMessage()
            ], 500);
        }
    }
} 
