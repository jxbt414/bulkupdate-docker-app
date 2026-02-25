<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use App\Models\LineItem;
use Exception;

class CsvService
{
    private const REQUIRED_HEADERS = [
        'line_item_id'
    ];

    private const ALLOWED_HEADERS = [
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
        'labels_remove',
        'targeting_presets'
    ];

    public function validateAndParseCsv(UploadedFile $file): array
    {
        try {
            // Use encoding="utf-8" for file operations
            $handle = fopen($file->getPathname(), 'r');
            if ($handle === false) {
                throw new Exception("Failed to open CSV file");
            }

            // Read headers
            $headers = fgetcsv($handle);
            Log::info("Raw headers read from CSV:", [
                'headers' => $headers,
                'first_header' => $headers[0] ?? 'null',
                'encoding' => mb_detect_encoding($headers[0] ?? ''),
                'length' => strlen($headers[0] ?? ''),
                'bytes' => bin2hex($headers[0] ?? '')
            ]);

            if (!$headers) {
                throw new Exception("Failed to read CSV headers");
            }

            // Ensure headers are properly trimmed and encoded
            $headers = array_map(function($header) {
                $header = trim($header);
                Log::info("Processing header:", [
                    'raw' => $header,
                    'trimmed' => trim($header),
                    'lowercase' => strtolower(trim($header))
                ]);
                return $header;
            }, $headers);

            $this->validateHeaders($headers);

            $data = [];
            $row = 2; // Start from row 2 (after headers)
            
            while (($record = fgetcsv($handle)) !== false) {
                try {
                    $lineItem = $this->parseRow($headers, $record, $row);
                    $data[] = $lineItem;
                } catch (Exception $e) {
                    Log::error("Error parsing row {$row}: " . $e->getMessage());
                    throw new Exception("Error in row {$row}: " . $e->getMessage());
                }
                $row++;
            }

            fclose($handle);
            return $data;
        } catch (Exception $e) {
            Log::error("CSV parsing error: " . $e->getMessage());
            throw $e;
        }
    }

    private function validateHeaders(array $headers): void
    {
        // Create a lowercase version of headers for case-insensitive comparison
        $lowercaseHeaders = array_map('strtolower', array_map('trim', $headers));
        $lowercaseRequired = array_map('strtolower', self::REQUIRED_HEADERS);
        $lowercaseAllowed = array_map('strtolower', self::ALLOWED_HEADERS);
        
        // Create a mapping of lowercase to original headers for error messages
        $headerCaseMap = array_combine($lowercaseHeaders, $headers);
        
        // Check for required headers with improved error message
        $missingRequired = array_diff($lowercaseRequired, $lowercaseHeaders);
        if (!empty($missingRequired)) {
            throw new Exception(sprintf(
                "Missing required field: line_item_id\nProvided headers: %s\nNote: Only line_item_id is required for dynamic updates",
                implode(', ', $headers)
            ));
        }

        // Check for invalid headers with improved error message
        $invalidHeaders = array_diff($lowercaseHeaders, $lowercaseAllowed);
        if (!empty($invalidHeaders)) {
            $invalidOriginalCase = array_intersect_key($headerCaseMap, array_flip($invalidHeaders));
            throw new Exception(sprintf(
                "Invalid headers found: %s\nAllowed headers: %s",
                implode(', ', $invalidOriginalCase),
                implode(', ', self::ALLOWED_HEADERS)
            ));
        }
    }

    private function parseRow(array $headers, array $record, int $row): array
    {
        if (count($headers) !== count($record)) {
            throw new Exception("Column count mismatch in row {$row}. Expected " . count($headers) . " columns but got " . count($record));
        }

        // Create a case-insensitive mapping of headers while preserving original case
        $headerMap = array_combine(
            array_map('strtolower', $headers),
            $headers
        );

        $data = array_combine($headers, $record);
        
        // Modified line item ID validation to handle programmatic line items
        $lineItemIdKey = $headerMap['line_item_id'] ?? null;
        $lineItemNameKey = $headerMap['line_item_name'] ?? null;
        
        if (!$lineItemIdKey || empty($data[$lineItemIdKey])) {
            // If line item ID is missing, check for line item name
            if (!$lineItemNameKey || empty($data[$lineItemNameKey])) {
                throw new Exception("Either line item ID or line item name is required in row {$row}");
            }
            
            try {
                // Try to fetch proposal line item ID using the name
                $proposalLineItemId = $this->getProposalLineItemIdByName($data[$lineItemNameKey]);
                if ($proposalLineItemId) {
                    $data[$lineItemIdKey] = $proposalLineItemId;
                } else {
                    throw new Exception("Could not find proposal line item with name: {$data[$lineItemNameKey]}");
                }
            } catch (Exception $e) {
                throw new Exception("Error in row {$row}: " . $e->getMessage());
            }
        }

        // Convert numeric fields using case-insensitive checks
        if (isset($headerMap['budget'])) {
            $budgetKey = $headerMap['budget'];
            if (!empty($data[$budgetKey])) {
                if (!is_numeric($data[$budgetKey])) {
                    throw new Exception("Budget must be a number in row {$row}");
                }
                $data[$budgetKey] = (float) $data[$budgetKey];
            }
        }

        if (isset($headerMap['priority'])) {
            $priorityKey = $headerMap['priority'];
            if (!empty($data[$priorityKey])) {
                if (!is_numeric($data[$priorityKey])) {
                    throw new Exception("Priority must be a number in row {$row}");
                }
                $data[$priorityKey] = (int) $data[$priorityKey];
            }
        }

        if (isset($headerMap['impression_goals'])) {
            $impressionGoalsKey = $headerMap['impression_goals'];
            if (!empty($data[$impressionGoalsKey])) {
                if (!is_numeric($data[$impressionGoalsKey])) {
                    throw new Exception("Impression goals must be a number in row {$row}");
                }
                $data[$impressionGoalsKey] = (int) $data[$impressionGoalsKey];
            }
        }

        return $data;
    }

    /**
     * Get proposal line item ID by name
     *
     * @param string $name
     * @return string|null
     */
    private function getProposalLineItemIdByName(string $name): ?string
    {
        try {
            // Get instance of Google Ad Manager service
            $googleAdManagerService = app(GoogleAdManagerService::class);
            
            // Create a PQL query to search for the proposal line item
            $statement = new \Google\AdsApi\AdManager\v202411\Statement();
            
            // Build the query with proper escaping for the name
            $query = sprintf("WHERE name = '%s'", str_replace("'", "\\'", $name));
            $statement->setQuery($query);

            // Log the query for debugging
            Log::info('Searching for proposal line item', [
                'name' => $name,
                'query' => $query
            ]);

            // Search for the proposal line item
            $result = $googleAdManagerService->getProposalLineItemService()->getProposalLineItemsByStatement($statement);
            
            if ($result->getResults() !== null && count($result->getResults()) > 0) {
                $proposalLineItem = $result->getResults()[0];
                
                // Log the found item
                Log::info('Found proposal line item', [
                    'id' => $proposalLineItem->getId(),
                    'name' => $proposalLineItem->getName()
                ]);
                
                return (string) $proposalLineItem->getId();
            }
            
            // Log when no results found
            Log::warning('No proposal line item found with name', [
                'name' => $name
            ]);
            
            return null;
        } catch (Exception $e) {
            Log::error('Error fetching proposal line item by name', [
                'name' => $name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new Exception("Failed to fetch proposal line item: " . $e->getMessage());
        }
    }
} 