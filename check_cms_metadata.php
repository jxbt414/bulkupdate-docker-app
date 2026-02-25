<?php

namespace Google\AdsApi\Examples\AdManager\v202408\LineItemService;

require __DIR__ . '/vendor/autoload.php';

use Exception;
use Google\AdsApi\AdManager\v202408\ApiException;
use Google\AdsApi\AdManager\AdManagerSession;
use Google\AdsApi\AdManager\AdManagerSessionBuilder;
use Google\AdsApi\AdManager\Util\v202408\StatementBuilder;
use Google\AdsApi\AdManager\v202408\ServiceFactory;
use Google\AdsApi\Common\OAuth2TokenBuilder;
use Google\AdsApi\AdManager\v202408\CustomCriteriaSet;
use Google\AdsApi\AdManager\v202408\CmsMetadataCriteria;
use Google\AdsApi\AdManager\v202408\LineItem;

// Constants
const INPUT_CSV = './../ufr.csv';
const OUTPUT_CSV = 'metadata_check_results.csv';
const TARGET_METADATA_IDS = ['16808396700', '19774857800', '16355486100'];
const TARGET_OPERATOR = 'NOT_EQUALS';

// Color constants
const COLOR_GREEN = "\033[32m";
const COLOR_BLUE = "\033[34m";
const COLOR_RED = "\033[31m";
const COLOR_YELLOW = "\033[33m";
const COLOR_RESET = "\033[0m";

/**
 * Check if a line item has the target CMS metadata criteria
 */
function checkLineItemMetadata($lineItemId, $serviceFactory, $session)
{
    try {
        echo COLOR_YELLOW . "Checking line item ID {$lineItemId} for CMS metadata criteria...\n" . COLOR_RESET;

        // Get the line item service
        $lineItemService = $serviceFactory->createLineItemService($session);

        // Create a statement to get the line item
        $statementBuilder = new StatementBuilder();
        $statementBuilder->Where('id = :id')
            ->WithBindVariableValue('id', $lineItemId);

        // Get the line item
        $page = $lineItemService->getLineItemsByStatement($statementBuilder->toStatement());

        if ($page->getResults() === null || count($page->getResults()) === 0) {
            echo COLOR_RED . "No line item found with ID: {$lineItemId}\n" . COLOR_RESET;
            return [
                'lineItemId' => $lineItemId,
                'lineItemName' => 'Not Found',
                'hasAllMetadata' => false,
                'metadataOperator' => 'N/A',
                'metadataValues' => [],
                'foundMetadataIds' => [],
                'missingMetadataIds' => TARGET_METADATA_IDS,
                'error' => 'Line item not found'
            ];
        }

        $lineItem = $page->getResults()[0];
        $lineItemName = $lineItem->getName();
        echo COLOR_BLUE . "Found line item: {$lineItemName}\n" . COLOR_RESET;

        // Check for CMS metadata criteria in the targeting
        $targeting = $lineItem->getTargeting();
        $customTargeting = $targeting->getCustomTargeting();

        $metadataOperator = null;
        $metadataValues = [];
        $foundMetadataIds = [];

        // Function to recursively check for CMS metadata criteria
        $checkForMetadata = function ($criteria) use (&$metadataOperator, &$metadataValues, &$foundMetadataIds, &$checkForMetadata) {
            if ($criteria instanceof CmsMetadataCriteria) {
                $valueIds = $criteria->getCmsMetadataValueIds();
                $metadataValues = array_merge($metadataValues, $valueIds);
                $currentOperator = $criteria->getOperator();

                // Check if any of our target IDs are in this criteria
                foreach (TARGET_METADATA_IDS as $targetId) {
                    if (in_array($targetId, $valueIds)) {
                        if ($metadataOperator === null) {
                            $metadataOperator = $currentOperator;
                        } elseif ($metadataOperator !== $currentOperator) {
                            echo COLOR_YELLOW . "Warning: Found different operators for metadata criteria\n" . COLOR_RESET;
                        }

                        if (!in_array($targetId, $foundMetadataIds)) {
                            $foundMetadataIds[] = $targetId;
                            echo COLOR_GREEN . "Found CMS metadata criteria with ID: {$targetId}, operator: {$currentOperator}\n" . COLOR_RESET;
                        }
                    }
                }
            } elseif ($criteria instanceof CustomCriteriaSet) {
                if ($criteria->getChildren() !== null) {
                    foreach ($criteria->getChildren() as $child) {
                        $checkForMetadata($child);
                    }
                }
            }
        };

        if ($customTargeting !== null) {
            $checkForMetadata($customTargeting);
        }

        // Check if all target metadata IDs were found
        $hasAllMetadata = count($foundMetadataIds) === count(TARGET_METADATA_IDS);
        $missingMetadataIds = array_diff(TARGET_METADATA_IDS, $foundMetadataIds);

        if ($hasAllMetadata) {
            echo COLOR_GREEN . "Line item has ALL required metadata IDs with operator: {$metadataOperator}\n" . COLOR_RESET;
        } else {
            echo COLOR_RED . "Line item is missing some required metadata IDs: " . implode(', ', $missingMetadataIds) . "\n" . COLOR_RESET;
        }

        return [
            'lineItemId' => $lineItemId,
            'lineItemName' => $lineItemName,
            'hasAllMetadata' => $hasAllMetadata,
            'metadataOperator' => $metadataOperator,
            'metadataValues' => $metadataValues,
            'foundMetadataIds' => $foundMetadataIds,
            'missingMetadataIds' => $missingMetadataIds,
            'error' => ''
        ];
    } catch (Exception $e) {
        echo COLOR_RED . "Error checking line item metadata: " . $e->getMessage() . "\n" . COLOR_RESET;
        return [
            'lineItemId' => $lineItemId,
            'lineItemName' => 'Error',
            'hasAllMetadata' => false,
            'metadataOperator' => 'Error',
            'metadataValues' => [],
            'foundMetadataIds' => [],
            'missingMetadataIds' => TARGET_METADATA_IDS,
            'error' => $e->getMessage()
        ];
    }
}

try {
    echo COLOR_GREEN . "Starting CMS Metadata Check Script for Line Items\n" . COLOR_RESET;
    echo COLOR_BLUE . "Checking for ALL metadata IDs: " . implode(', ', TARGET_METADATA_IDS) . "\n" . COLOR_RESET;

    // Initialize results array
    $results = [];

    // Create OAuth2 credentials
    echo COLOR_BLUE . "Building OAuth2 credentials...\n" . COLOR_RESET;
    $oAuth2Credential = (new OAuth2TokenBuilder())
        ->fromFile('./adsapi_php.ini')
        ->build();

    // Create session
    echo COLOR_BLUE . "Creating Ad Manager session...\n" . COLOR_RESET;
    $session = (new AdManagerSessionBuilder())
        ->fromFile('./adsapi_php.ini')
        ->withOAuth2Credential($oAuth2Credential)
        ->build();

    $serviceFactory = new ServiceFactory();

    // Read input CSV
    echo COLOR_BLUE . "Reading input CSV file...\n" . COLOR_RESET;
    if (!file_exists(INPUT_CSV)) {
        throw new Exception("Input CSV file not found: " . INPUT_CSV);
    }

    try {
        $handle = fopen(INPUT_CSV, "r");
        if ($handle === false) {
            throw new Exception("Failed to open input CSV file");
        }

        // Skip header row if present
        if (($row = fgetcsv($handle)) !== false) {
            // Check if this looks like a header row
            if (!is_numeric(trim($row[0]))) {
                echo COLOR_YELLOW . "Skipping header row\n" . COLOR_RESET;
            } else {
                // If it's not a header, rewind to process this row
                rewind($handle);
            }
        }

        // Process each line item
        while (($row = fgetcsv($handle)) !== false) {
            $lineItemId = trim($row[0]);
            echo COLOR_YELLOW . "Processing line item: " . $lineItemId . "\n" . COLOR_RESET;

            try {
                // Check the line item for metadata
                $result = checkLineItemMetadata($lineItemId, $serviceFactory, $session);
                $results[] = [
                    'Line Item ID' => $result['lineItemId'],
                    'Line Item Name' => $result['lineItemName'],
                    'Has All Target Metadata' => $result['hasAllMetadata'] ? 'Yes' : 'No',
                    'Metadata Operator' => $result['metadataOperator'] ?: 'N/A',
                    'Found Metadata IDs' => !empty($result['foundMetadataIds']) ? implode(', ', $result['foundMetadataIds']) : 'None',
                    'Missing Metadata IDs' => !empty($result['missingMetadataIds']) ? implode(', ', $result['missingMetadataIds']) : 'None',
                    'All CMS Metadata Values' => !empty($result['metadataValues']) ? implode(', ', $result['metadataValues']) : 'None',
                    'Error' => $result['error']
                ];

                echo COLOR_GREEN . "Successfully processed line item: " . $lineItemId . "\n" . COLOR_RESET;
            } catch (Exception $e) {
                echo COLOR_RED . "Error processing line item " . $lineItemId . ": " . $e->getMessage() . "\n" . COLOR_RESET;
                $results[] = [
                    'Line Item ID' => $lineItemId,
                    'Line Item Name' => 'ERROR',
                    'Has All Target Metadata' => 'Error',
                    'Metadata Operator' => 'Error',
                    'Found Metadata IDs' => 'Error',
                    'Missing Metadata IDs' => 'Error',
                    'All CMS Metadata Values' => 'Error',
                    'Error' => $e->getMessage()
                ];
            }
        }

        fclose($handle);
    } catch (Exception $e) {
        throw new Exception("Error reading CSV: " . $e->getMessage());
    }

    // Write results to CSV
    echo COLOR_BLUE . "Writing results to " . OUTPUT_CSV . "...\n" . COLOR_RESET;
    try {
        $outputHandle = fopen(OUTPUT_CSV, 'w');
        if ($outputHandle === false) {
            throw new Exception("Failed to open output CSV file");
        }

        // Write header
        fputcsv($outputHandle, [
            'Line Item ID',
            'Line Item Name',
            'Has All Target Metadata',
            'Metadata Operator',
            'Found Metadata IDs',
            'Missing Metadata IDs',
            'All CMS Metadata Values',
            'Error'
        ]);

        // Write data
        foreach ($results as $result) {
            fputcsv($outputHandle, $result);
        }

        fclose($outputHandle);
        echo COLOR_GREEN . "Results written to " . OUTPUT_CSV . "\n" . COLOR_RESET;
    } catch (Exception $e) {
        throw new Exception("Error writing CSV: " . $e->getMessage());
    }

    // Write to instructions.txt
    try {
        $instructionsContent = "--- Check CMS metadata criteria in line items ---\n";

        // Check if instructions.txt exists
        if (file_exists('instructions.txt')) {
            // Append to existing file
            file_put_contents('instructions.txt', $instructionsContent, FILE_APPEND);
        } else {
            // Create new file
            file_put_contents('instructions.txt', $instructionsContent);
        }
    } catch (Exception $e) {
        echo COLOR_RED . "Error writing to instructions.txt: " . $e->getMessage() . "\n" . COLOR_RESET;
    }

    // Write to project_description.txt
    try {
        $projectDescription = "Project Title: CMS Metadata Checker for Google Ad Manager Line Items\n\n";
        $projectDescription .= "Project Description:\n";
        $projectDescription .= "A tool to check Google Ad Manager line items for specific CMS metadata criteria settings. The script takes line item IDs from a CSV file and verifies if they have ALL the metadata criteria IDs " . implode(', ', TARGET_METADATA_IDS) . " set with the NOT_EQUALS operator.\n\n";
        $projectDescription .= "Special Instructions:\n";
        $projectDescription .= "- Process line item IDs directly from CSV\n";
        $projectDescription .= "- Check that ALL required CMS metadata criteria IDs are present\n";
        $projectDescription .= "- Verify the operator is set to NOT_EQUALS\n";
        $projectDescription .= "- Generate a detailed report in CSV format\n";
        $projectDescription .= "- Include line item name in the output\n";
        $projectDescription .= "- List both found and missing metadata IDs\n";
        $projectDescription .= "- Handle cases where no line item is found\n";

        file_put_contents('project_description.txt', $projectDescription);
    } catch (Exception $e) {
        echo COLOR_RED . "Error writing project description: " . $e->getMessage() . "\n" . COLOR_RESET;
    }

    // Write to summary.txt
    try {
        $summaryContent = "- Checked line items directly for ALL CMS metadata criteria (IDs: " . implode(', ', TARGET_METADATA_IDS) . ")\n";
        $summaryContent .= "- Verified operator is set to NOT_EQUALS\n";
        $summaryContent .= "- Generated report in metadata_check_results.csv with line item details and missing metadata IDs\n";

        // Check if summary.txt exists
        if (file_exists('summary.txt')) {
            // Append to existing file
            file_put_contents('summary.txt', $summaryContent, FILE_APPEND);
        } else {
            // Create new file
            file_put_contents('summary.txt', $summaryContent);
        }
    } catch (Exception $e) {
        echo COLOR_RED . "Error writing summary: " . $e->getMessage() . "\n" . COLOR_RESET;
    }

    echo COLOR_GREEN . "Script completed successfully\n" . COLOR_RESET;
} catch (Exception $e) {
    echo COLOR_RED . "Critical error: " . $e->getMessage() . "\n" . COLOR_RESET;
    exit(1);
}
