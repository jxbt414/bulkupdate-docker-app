<?php

// namespace Google\AdsApi\Examples\AdManager\v202408\LineItemService;

require __DIR__ . '/vendor/autoload.php';

use Google\AdsApi\AdManager\v202408\ApiException;
use Google\AdsApi\AdManager\v202408\ApplicationException;
use Google\AdsApi\AdManager\AdManagerSession;
use Google\AdsApi\AdManager\AdManagerSessionBuilder;
use Google\AdsApi\AdManager\Util\v202408\StatementBuilder;
use Google\AdsApi\AdManager\v202408\ServiceFactory;
use Google\AdsApi\Common\OAuth2TokenBuilder;
use Google\AdsApi\AdManager\v202408\CustomCriteria;
use Google\AdsApi\AdManager\v202408\CustomCriteriaSet;
use Google\AdsApi\AdManager\v202408\CustomCriteriaComparisonOperator;
use Google\AdsApi\AdManager\v202408\CustomCriteriaSetLogicalOperator;
use Google\AdsApi\AdManager\v202408\ProposalLineItem;
use Google\AdsApi\AdManager\v202408\Location;
use Google\AdsApi\AdManager\v202408\GeoTargeting;
use Google\AdsApi\AdManager\v202408\TechnologyTargeting;
use Google\AdsApi\AdManager\v202408\DeviceCategory;
use Google\AdsApi\AdManager\v202408\DeviceCategoryTargeting;
use Google\AdsApi\AdManager\v202408\DayPartTargeting;
use Google\AdsApi\AdManager\v202408\TimeOfDay;
use Google\AdsApi\AdManager\v202408\DayPart;
use Google\AdsApi\AdManager\v202408\AdUnitTargeting;
use Google\AdsApi\AdManager\v202408\PlacementTargeting;
use Google\AdsApi\AdManager\v202408\InventoryTargeting;
use Google\AdsApi\AdManager\v202408\ProposalAction;
use Google\AdsApi\AdManager\v202408\CmsMetadataCriteria;
use Google\AdsApi\AdManager\v202408\EditProposalsForNegotiation;
use Google\AdsApi\AdManager\v202408\UpdateOrderWithSellerData;
use Google\AdsApi\AdManager\v202408\RequestBuyerAcceptance;
use Google\AdsApi\AdManager\v202408\AdUnitService;
use Google\AdsApi\AdManager\v202408\Targeting;
use Google\AdsApi\AdManager\v202408\Label;
use Google\AdsApi\AdManager\v202408\AppliedLabel;
use Google\AdsApi\AdManager\v202408\Contact;
use Google\AdsApi\AdManager\v202408\DateTime as GoogleDateTime;
use Google\AdsApi\AdManager\v202408\Date as GoogleDate;
use Google\AdsApi\AdManager\v202408\RequestPlatformTargeting;
use Google\AdsApi\AdManager\v202408\RequestPlatform;
use Google\AdsApi\AdManager\v202408\AudienceSegmentService;
use Google\AdsApi\AdManager\v202408\AudienceSegmentCriteria;
use Google\AdsApi\AdManager\v202408\CustomTargetingService;
use DateTime;
use ReflectionClass;

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Constants for CSV fields
const CSV_FIELDS = [
    'lineItemId',          // Line Item ID
    'lineItemName',        // Line Item Name
    'labelNames',          // Label names
    'labelOperation',      // Add/remove labels
    'deliveryRateType',    // Pacing
    'adUnitNames',         // Ad unit names
    'adUnitOperation',     // Add/remove ad units
    'postcodes',           // Postcodes
    'postcodeOperation',   // Add/remove Postcode
    'keyValue',           // Keyvalue REMOVE
    'audienceSegmentName', // Audience segment name
    'audienceOperation'    // Add/remove audience segment
];

// Add mapping functions
function getLabelIdsByNames(ServiceFactory $serviceFactory, AdManagerSession $session, array $labelNames)
{
    echo "\n=== Getting Label IDs for Names ===\n";
    $labelService = $serviceFactory->createLabelService($session);
    $labelIds = [];

    // Build a query to fetch only the specific labels we need
    $conditions = [];
    foreach ($labelNames as $name) {
        $name = trim($name);
        $conditions[] = "name = '" . str_replace("'", "\\'", $name) . "'";
    }

    if (empty($conditions)) {
        echo "No label names provided to search\n";
        return $labelIds;
    }

    $statementBuilder = new StatementBuilder();
    $statementBuilder->Where(implode(' OR ', $conditions));
    echo "Debug: Searching for labels with query: " . $statementBuilder->toStatement()->getQuery() . "\n";

    $result = $labelService->getLabelsByStatement($statementBuilder->toStatement());

    if ($result->getResults() !== null) {
        foreach ($result->getResults() as $label) {
            $labelIds[] = $label->getId();
            echo "✓ Found label: Name='" . $label->getName() . "' ID=" . $label->getId() . "\n";
        }
    }

    if (empty($labelIds)) {
        echo "⚠ WARNING: No matching labels found for the provided names\n";
        echo "Searched for: " . implode(", ", $labelNames) . "\n";
    } else {
        echo "\nFinal mapped label IDs: " . implode(", ", $labelIds) . "\n";
    }

    return $labelIds;
}

function getAdUnitIdsByNames(ServiceFactory $serviceFactory, AdManagerSession $session, array $adUnitNames)
{
    $inventoryService = $serviceFactory->createInventoryService($session);
    $adUnitIds = [];
    $adUnitMap = [];

    // Build a query to fetch only the specific ad units we need
    $conditions = [];
    foreach ($adUnitNames as $name) {
        $conditions[] = "name = '" . str_replace("'", "\\'", trim($name)) . "'";
    }

    $statementBuilder = new StatementBuilder();
    $statementBuilder->Where(implode(' OR ', $conditions));
    echo "Debug: Searching for ad units with query: " . $statementBuilder->toStatement()->getQuery() . "\n";

    $result = $inventoryService->getAdUnitsByStatement($statementBuilder->toStatement());

    if ($result->getResults() !== null) {
        foreach ($result->getResults() as $adUnit) {
            $adUnitMap[$adUnit->getName()] = $adUnit->getId();
        }
    }

    // Map names to IDs
    foreach ($adUnitNames as $name) {
        $name = trim($name);
        if (isset($adUnitMap[$name])) {
            $adUnitIds[] = $adUnitMap[$name];
            echo "Mapped ad unit name '$name' to ID: {$adUnitMap[$name]}\n";
        } else {
            echo "Warning: Ad unit name '$name' not found\n";
        }
    }

    return $adUnitIds;
}

function updateLabels($existingLabels, $newLabelIds, $operation)
{
    echo "\n=== Label Update Details ===\n";
    echo "Operation: $operation\n";

    // Initialize arrays and convert to integers
    $existingLabels = $existingLabels ?: [];
    $newLabelIds = array_map('intval', $newLabelIds);

    echo "Number of existing labels: " . count($existingLabels) . "\n";
    echo "Label IDs to process: " . implode(", ", $newLabelIds) . "\n\n";

    // Show current labels
    echo "Current labels on item:\n";
    $existingLabelIds = [];
    foreach ($existingLabels as $label) {
        $labelId = intval($label->getLabelId());
        $existingLabelIds[] = $labelId;
        echo "- Label ID: $labelId\n";
    }

    if ($operation === 'REMOVE') {
        echo "\nStarting REMOVE operation...\n";
        $updatedLabels = [];
        $removedCount = 0;
        $keptCount = 0;

        // Process each existing label
        foreach ($existingLabels as $label) {
            $currentLabelId = intval($label->getLabelId());
            if (!in_array($currentLabelId, $newLabelIds, true)) {
                $updatedLabels[] = $label;
                echo "✓ Keeping label ID: $currentLabelId (not in removal list)\n";
                $keptCount++;
            } else {
                echo "✗ Removing label ID: $currentLabelId\n";
                $removedCount++;
            }
        }

        echo "\nRemoval Summary:\n";
        echo "- Labels before removal: " . count($existingLabels) . "\n";
        echo "- Labels removed: $removedCount\n";
        echo "- Labels kept: $keptCount\n";
        echo "- Final label count: " . count($updatedLabels) . "\n";

        // If all labels were removed, ensure we return an empty array
        return $updatedLabels;
    } elseif ($operation === 'ADD') {
        echo "\nStarting ADD operation...\n";
        $updatedLabels = $existingLabels;
        $addedCount = 0;

        foreach ($newLabelIds as $labelId) {
            if (!in_array($labelId, $existingLabelIds)) {
                $appliedLabel = new AppliedLabel();
                $appliedLabel->setLabelId($labelId);
                $updatedLabels[] = $appliedLabel;
                echo "Added new label ID: $labelId\n";
                $addedCount++;
            } else {
                echo "Label ID already exists: $labelId\n";
            }
        }

        echo "\nAddition Summary:\n";
        echo "- Labels before addition: " . count($existingLabels) . "\n";
        echo "- Labels added: $addedCount\n";
        echo "- Labels after addition: " . count($updatedLabels) . "\n";

        return $updatedLabels;
    }

    echo "\nNo changes made to labels (invalid operation)\n";
    return $existingLabels;
}

function updateAdUnits($existingAdUnits, $newAdUnitIds, $operation, $inventoryService)
{
    $existingAdUnitIds = [];
    $activeAdUnits = [];
    $inactiveAdUnits = [];

    // Process existing ad units
    foreach ($existingAdUnits as $adUnit) {
        $adUnitId = $adUnit->getAdUnitId();
        $existingAdUnitIds[] = $adUnitId;

        // Check ad unit status
        $adUnitBuilder = new StatementBuilder();
        $adUnitBuilder->Where('id = :id')
            ->WithBindVariableValue('id', $adUnitId);

        $result = $inventoryService->getAdUnitsByStatement($adUnitBuilder->toStatement());

        if ($result->getResults() !== null && !empty($result->getResults())) {
            $fetchedAdUnit = $result->getResults()[0];
            echo "DEBUG: Ad unit $adUnitId status from API: " . $fetchedAdUnit->getStatus() . "\n";
            if ($fetchedAdUnit->getStatus() === 'ACTIVE') {
                if ($operation === 'REMOVE' && in_array($adUnitId, $newAdUnitIds)) {
                    echo "Removing ad unit $adUnitId as requested\n";
                } else {
                    $activeAdUnits[] = $adUnit;
                    echo "Keeping ad unit $adUnitId in targeting\n";
                }
            } else {
                $inactiveAdUnits[] = $adUnitId;
                echo "Removing inactive ad unit $adUnitId from targeting (status: " . $fetchedAdUnit->getStatus() . ")\n";
            }
        } else {
            $inactiveAdUnits[] = $adUnitId;
            echo "Removing non-existent ad unit $adUnitId from targeting\n";
        }
    }

    // Add new ad units if operation is ADD
    if ($operation === 'ADD') {
        foreach ($newAdUnitIds as $adUnitId) {
            if (!in_array($adUnitId, $existingAdUnitIds)) {
                $adUnitBuilder = new StatementBuilder();
                $adUnitBuilder->Where('id = :id')
                    ->WithBindVariableValue('id', $adUnitId);

                $result = $inventoryService->getAdUnitsByStatement($adUnitBuilder->toStatement());

                if ($result->getResults() !== null && !empty($result->getResults())) {
                    $fetchedAdUnit = $result->getResults()[0];
                    echo "DEBUG: Ad unit $adUnitId status from API: " . $fetchedAdUnit->getStatus() . "\n";
                    if ($fetchedAdUnit->getStatus() === 'ACTIVE') {
                        $adUnitTargeting = new AdUnitTargeting();
                        $adUnitTargeting->setAdUnitId($adUnitId);
                        $adUnitTargeting->setIncludeDescendants(true);
                        $existingAdUnits[] = $adUnitTargeting;
                        echo "Added new targeting for active ad unit: $adUnitId\n";
                    } else {
                        echo "Skipping inactive ad unit: $adUnitId (status: " . $fetchedAdUnit->getStatus() . ")\n";
                    }
                } else {
                    echo "Skipping non-existent ad unit: $adUnitId\n";
                }
            } else {
                echo "Ad unit targeting already exists: $adUnitId\n";
            }
        }
    }

    if (!empty($inactiveAdUnits)) {
        echo "Removed " . count($inactiveAdUnits) . " inactive ad unit(s) from targeting\n";
    }

    return $activeAdUnits;
}

function getLocationIdForPostcode($postcode)
{
    try {
        $geotargetsFile = __DIR__ . '/geotargets-2025-01-13.csv';
        if (!file_exists($geotargetsFile)) {
            throw new Exception("Geotargets file not found at: " . $geotargetsFile);
        }

        $handle = fopen($geotargetsFile, 'r');
        if ($handle === false) {
            throw new Exception("Failed to open geotargets file");
        }

        // Read header row to find column positions
        $header = fgetcsv($handle);
        if ($header === false) {
            throw new Exception("Failed to read header row from geotargets file");
        }

        $criteriaIdCol = array_search('Criteria ID', $header);
        $nameCol = array_search('Name', $header);
        $countryCodeCol = array_search('Country Code', $header);
        $statusCol = array_search('Status', $header);
        $parentIdCol = array_search('Parent ID', $header);
        $typeCol = array_search('Target Type', $header);

        if (
            $criteriaIdCol === false || $nameCol === false || $countryCodeCol === false ||
            $statusCol === false || $parentIdCol === false || $typeCol === false
        ) {
            throw new Exception("Required columns not found in geotargets file");
        }

        // Search for the postcode
        while (($row = fgetcsv($handle)) !== false) {
            if (
                $row[$countryCodeCol] === 'AU' &&
                $row[$typeCol] === 'Postal Code' &&
                $row[$statusCol] === 'Active' &&
                $row[$nameCol] === $postcode
            ) {

                fclose($handle);
                $location = new Location();
                $location->setId((int)$row[$criteriaIdCol]);
                $location->setType('POSTAL_CODE');
                $location->setCanonicalParentId((int)$row[$parentIdCol]);
                $location->setDisplayName($postcode);
                error_log("Debug: Found location ID " . $row[$criteriaIdCol] . " for postcode " . $postcode);
                return $location;
            }
        }

        fclose($handle);
        error_log("Warning: No mapping found for postcode: " . $postcode . " in geotargets file");
        return null;
    } catch (Exception $e) {
        error_log("Error reading geotargets file: " . $e->getMessage());
        return null;
    }
}

function updateGeoTargeting($service, $lineItem, $postcodes)
{
    try {
        // Get targeting object first
        $targeting = $lineItem->getTargeting();
        if ($targeting === null) {
            $targeting = new Targeting();
        }

        // Get current geo targeting through targeting object
        $currentGeoTargeting = $targeting->getGeoTargeting();
        $locations = [];

        foreach ($postcodes as $postcode) {
            $location = getLocationIdForPostcode($postcode);
            if ($location !== null) {
                $locations[] = $location;
            }
        }

        if (empty($locations)) {
            error_log("Warning: No valid location IDs found for any postcodes");
            return false;
        }

        $geoTargeting = new GeoTargeting();
        $geoTargeting->setTargetedLocations($locations);
        $targeting->setGeoTargeting($geoTargeting);
        $lineItem->setTargeting($targeting);

        return true;
    } catch (Exception $e) {
        error_log("Error updating geo targeting: " . $e->getMessage());
        return false;
    }
}

function printCustomTargetingDetails($customTargeting, $indent = 0)
{
    $indentStr = str_repeat("  ", $indent);

    if ($customTargeting === null) {
        echo $indentStr . "Custom Targeting: NULL\n";
        return;
    }

    if (!is_array($customTargeting)) {
        echo $indentStr . "Custom Targeting: Not an array - " . get_class($customTargeting) . "\n";
        return;
    }

    echo $indentStr . "Custom Targeting Array (" . count($customTargeting) . " items):\n";

    foreach ($customTargeting as $index => $item) {
        if ($item instanceof CustomCriteriaSet) {
            echo $indentStr . "- CriteriaSet #" . ($index + 1) . ":\n";
            echo $indentStr . "  Logical Operator: " . $item->getLogicalOperator() . "\n";

            $children = $item->getChildren();
            if ($children !== null) {
                echo $indentStr . "  Children (" . count($children) . "):\n";
                foreach ($children as $childIndex => $child) {
                    if ($child instanceof CustomCriteriaSet) {
                        printCustomTargetingDetails([$child], $indent + 2);
                    } elseif ($child instanceof AudienceSegmentCriteria) {
                        echo $indentStr . "    - AudienceSegmentCriteria:\n";
                        echo $indentStr . "      Segment IDs: " . implode(", ", $child->getAudienceSegmentIds()) . "\n";
                    } elseif ($child instanceof CustomCriteria) {
                        echo $indentStr . "    - CustomCriteria:\n";
                        echo $indentStr . "      KeyId: " . $child->getKeyId() . "\n";
                        echo $indentStr . "      ValueIds: " . implode(", ", $child->getValueIds()) . "\n";
                        echo $indentStr . "      Operator: " . $child->getOperator() . "\n";
                    } else {
                        echo $indentStr . "    - Unknown child type: " . get_class($child) . "\n";
                    }
                }
            } else {
                echo $indentStr . "  No children\n";
            }
        } else {
            echo $indentStr . "- Unknown type: " . get_class($item) . "\n";
        }
    }
}

class UpdateLineItems
{
    public static function runExample(
        ServiceFactory $serviceFactory,
        AdManagerSession $session,
        array $lineItemData
    ) {
        try {
            echo "Debug: Starting process for Line Item: {$lineItemData['lineItemId']}\n";
            echo "Debug: Full line item data: " . print_r($lineItemData, true) . "\n";

            $lineItemService = $serviceFactory->createLineItemService($session);
            echo "Debug: Created LineItemService\n";

            // Get the existing line item first
            $statementBuilder = new StatementBuilder();
            $statementBuilder->Where('id = :id')
                ->WithBindVariableValue('id', $lineItemData['lineItemId']);

            echo "Debug: Executing statement for line item ID: " . $lineItemData['lineItemId'] . "\n";

            $result = $lineItemService->getLineItemsByStatement(
                $statementBuilder->toStatement()
            );

            if ($result->getResults() === null || empty($result->getResults())) {
                echo "Error: Line item not found with ID: {$lineItemData['lineItemId']}\n";
                return;
            }

            $lineItem = $result->getResults()[0];
            echo "Debug: Found existing line item with ID: " . $lineItem->getId() . "\n";
            echo "Debug: Line item status: " . $lineItem->getStatus() . "\n";

            // Create a new targeting object and set request platform targeting first
            $targeting = new Targeting();
            $requestPlatformTargeting = new RequestPlatformTargeting();
            $requestPlatformTargeting->setTargetedRequestPlatforms([RequestPlatform::BROWSER]);
            $targeting->setRequestPlatformTargeting($requestPlatformTargeting);

            // Get existing targeting to preserve settings
            $existingTargeting = $lineItem->getTargeting();

            // Always set inventory targeting first as it's required
            $inventoryTargeting = new InventoryTargeting();
            if ($existingTargeting !== null && $existingTargeting->getInventoryTargeting() !== null) {
                echo "Debug: Using existing inventory targeting\n";
                $inventoryTargeting = $existingTargeting->getInventoryTargeting();
            } else {
                echo "Debug: Creating new inventory targeting\n";
                // If no existing targeting, we need to get the root ad unit
                $inventoryService = $serviceFactory->createInventoryService($session);
                $statementBuilder = new StatementBuilder();
                $statementBuilder->Where('parentId IS NULL');
                $result = $inventoryService->getAdUnitsByStatement($statementBuilder->toStatement());

                if ($result->getResults() !== null && !empty($result->getResults())) {
                    $rootAdUnit = $result->getResults()[0];
                    $adUnitTargeting = new AdUnitTargeting();
                    $adUnitTargeting->setAdUnitId($rootAdUnit->getId());
                    $adUnitTargeting->setIncludeDescendants(true);
                    $inventoryTargeting->setTargetedAdUnits([$adUnitTargeting]);
                    echo "Debug: Set targeting to root ad unit: " . $rootAdUnit->getId() . "\n";
                }
            }
            $targeting->setInventoryTargeting($inventoryTargeting);

            // Copy over other existing targeting that we're not modifying
            if ($existingTargeting !== null) {
                if ($existingTargeting->getTechnologyTargeting() !== null) {
                    $targeting->setTechnologyTargeting($existingTargeting->getTechnologyTargeting());
                }
                if ($existingTargeting->getDayPartTargeting() !== null) {
                    $targeting->setDayPartTargeting($existingTargeting->getDayPartTargeting());
                }
                if ($existingTargeting->getCustomTargeting() !== null) {
                    $targeting->setCustomTargeting($existingTargeting->getCustomTargeting());
                }
                if ($existingTargeting->getGeoTargeting() !== null) {
                    $targeting->setGeoTargeting($existingTargeting->getGeoTargeting());
                }
            }

            // Try to update with base targeting first
            try {
                $lineItem->setTargeting($targeting);
                $result = $lineItemService->updateLineItems([$lineItem]);
                echo "Debug: Successfully set base targeting\n";
            } catch (ApiException $e) {
                echo "Warning: Failed to set base targeting: " . $e->getMessage() . "\n";
                foreach ($e->getErrors() as $error) {
                    echo "- " . $error->getErrorString() . " @ " . $error->getFieldPath() . "\n";
                }
                return;
            }

            // Now handle additional targeting updates
            if (!empty($lineItemData['adUnitIds'])) {
                echo "\nProcessing inventory targeting...\n";
                $inventoryTargeting = new InventoryTargeting();

                // Get existing ad units if we're adding
                if (
                    $lineItemData['adUnitOperation'] === 'ADD' && $existingTargeting !== null &&
                    $existingTargeting->getInventoryTargeting() !== null
                ) {
                    $existingAdUnits = $existingTargeting->getInventoryTargeting()->getTargetedAdUnits() ?? [];
                } else {
                    $existingAdUnits = [];
                }

                $updatedAdUnits = updateAdUnits(
                    $existingAdUnits,
                    array_map('trim', explode(';', $lineItemData['adUnitIds'])),
                    $lineItemData['adUnitOperation'],
                    $serviceFactory->createInventoryService($session)
                );

                $inventoryTargeting->setTargetedAdUnits($updatedAdUnits);
                $targeting->setInventoryTargeting($inventoryTargeting);
            }

            // Handle geo targeting
            if (!empty($lineItemData['postcodes'])) {
                echo "\nProcessing geo targeting...\n";
                $locations = [];
                $postcodes = array_map('trim', explode(';', $lineItemData['postcodes']));

                foreach ($postcodes as $postcode) {
                    $location = getLocationIdForPostcode($postcode);
                    if ($location !== null) {
                        $locations[] = $location;
                    }
                }

                if (!empty($locations)) {
                    $geoTargeting = new GeoTargeting();
                    $geoTargeting->setTargetedLocations($locations);
                    $targeting->setGeoTargeting($geoTargeting);
                }
            }

            // Set the targeting on the line item
            $lineItem->setTargeting($targeting);

            // Update delivery settings if specified
            if (!empty($lineItemData['deliveryRateType'])) {
                echo "\nUpdating delivery rate type to: " . $lineItemData['deliveryRateType'] . "\n";
                $lineItem->setDeliveryRateType($lineItemData['deliveryRateType']);
            }

            // Update the line item with all changes at once
            try {
                $result = $lineItemService->updateLineItems([$lineItem]);
                if ($result !== null && !empty($result)) {
                    echo "Successfully updated line item " . $result[0]->getId() . "\n";
                }
            } catch (ApiException $e) {
                $errors = $e->getErrors();
                echo "API Exception:\n";
                foreach ($errors as $error) {
                    echo sprintf(
                        "[%s @ %s; trigger:'%s']\n",
                        $error->getErrorString(),
                        $error->getFieldPath(),
                        $error->getTrigger()
                    );
                }
                echo "Error processing line item " . $lineItem->getId() . ": ";
                foreach ($errors as $error) {
                    echo "[" . $error->getErrorString() . " @ " . $error->getFieldPath() . "]\n";
                }
                return;  // Exit the function instead of continue
            }
        } catch (Exception $e) {
            echo "Critical Error: " . get_class($e) . "\n";
            echo "Message: " . $e->getMessage() . "\n";
            echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
            global $lineNotUpdated;
            $lineNotUpdated[] = [$lineItemData['lineItemId'], $e->getMessage()];
        }
    }

    public static function main($lineItemData)
    {
        try {
            echo "Debug: Starting authentication process\n";
            echo "Debug: Looking for adsapi_php.ini in: " . realpath('./adsapi_php.ini') . "\n";

            if (!file_exists('./adsapi_php.ini')) {
                throw new Exception("adsapi_php.ini file not found in " . realpath('./'));
            }

            $oAuth2Credential = (new OAuth2TokenBuilder())
                ->fromFile('./adsapi_php.ini')
                ->build();
            echo "Debug: OAuth2 credential built successfully\n";

            $session = (new AdManagerSessionBuilder())
                ->fromFile('./adsapi_php.ini')
                ->withOAuth2Credential($oAuth2Credential)
                ->build();
            echo "Debug: Session built successfully\n";

            self::runExample(
                new ServiceFactory(),
                $session,
                $lineItemData
            );
        } catch (Exception $e) {
            echo "Critical Error in main(): " . get_class($e) . "\n";
            echo "Message: " . $e->getMessage() . "\n";
            echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
        }
    }
}

class UpdateProgrammaticLineItems
{
    public static function runExample(
        ServiceFactory $serviceFactory,
        AdManagerSession $session,
        array $lineItemData
    ) {
        try {
            echo "Debug: Starting process for Line Item: {$lineItemData['lineItemId']}\n";

            // Get the proposal line item using the line item ID directly
            $proposalLineItemService = $serviceFactory->createProposalLineItemService($session);
            $statementBuilder = new StatementBuilder();
            $statementBuilder->Where('lineItemId = :lineItemId')
                ->WithBindVariableValue('lineItemId', $lineItemData['lineItemId']);

            $result = $proposalLineItemService->getProposalLineItemsByStatement(
                $statementBuilder->toStatement()
            );

            if ($result->getResults() === null || empty($result->getResults())) {
                throw new Exception("No proposal line item found for line item ID: {$lineItemData['lineItemId']}");
            }

            $proposalLineItem = $result->getResults()[0];
            echo "Debug: Found proposal line item with ID: " . $proposalLineItem->getId() . "\n";

            // Get the proposal
            $proposalId = $proposalLineItem->getProposalId();
            echo "Debug: Found proposal ID: " . $proposalId . "\n";

            $proposalService = $serviceFactory->createProposalService($session);
            $proposalBuilder = new StatementBuilder();
            $proposalBuilder->Where('id = :id AND isArchived = false')
                ->WithBindVariableValue('id', $proposalId);

            $proposalResult = $proposalService->getProposalsByStatement(
                $proposalBuilder->toStatement()
            );

            if ($proposalResult->getResults() === null || empty($proposalResult->getResults())) {
                throw new Exception("No proposal found for proposal ID: $proposalId");
            }

            $proposal = $proposalResult->getResults()[0];
            echo "\n=== Initial Proposal State ===\n";
            echo "- ID: " . $proposal->getId() . "\n";
            echo "- Status: " . $proposal->getStatus() . "\n";
            echo "- Is Archived: " . ($proposal->getIsArchived() ? 'Yes' : 'No') . "\n";
            echo "- Is Sold: " . ($proposal->getIsSold() ? 'Yes' : 'No') . "\n";

            // STEP 1: Handle proposal reopening first
            if ($proposal->getStatus() !== 'DRAFT') {
                echo "\n=== STEP 1: Reopening Proposal ===\n";
                echo "Current proposal status: " . $proposal->getStatus() . "\n";

                try {
                    // First attempt to reopen
                    $proposalAction = new EditProposalsForNegotiation();
                    $result = $proposalService->performProposalAction(
                        $proposalAction,
                        $proposalBuilder->toStatement()
                    );

                    // Wait briefly for the action to take effect
                    sleep(2);

                    // Check status after first attempt
                    $proposalResult = $proposalService->getProposalsByStatement(
                        $proposalBuilder->toStatement()
                    );
                    $proposal = $proposalResult->getResults()[0];
                    echo "Status after first reopen attempt: " . $proposal->getStatus() . "\n";

                    // If still not in DRAFT, try one more time
                    if ($proposal->getStatus() !== 'DRAFT') {
                        echo "First attempt didn't result in DRAFT status. Trying again...\n";
                        $result = $proposalService->performProposalAction(
                            $proposalAction,
                            $proposalBuilder->toStatement()
                        );

                        sleep(2);

                        // Final status check
                        $proposalResult = $proposalService->getProposalsByStatement(
                            $proposalBuilder->toStatement()
                        );
                        $proposal = $proposalResult->getResults()[0];
                        echo "Status after second reopen attempt: " . $proposal->getStatus() . "\n";
                    }

                    if ($proposal->getStatus() !== 'DRAFT') {
                        // If proposal is approved and sold, we'll try to proceed with changes
                        if ($proposal->getStatus() === 'APPROVED' && $proposal->getIsSold()) {
                            echo "\nProposal is APPROVED and SOLD. Will attempt direct updates.\n";
                            // Don't throw an exception, just continue with changes
                        } else {
                            throw new Exception("Failed to reopen proposal - current status: " . $proposal->getStatus());
                        }
                    } else {
                        echo "Successfully reopened proposal for editing\n";
                    }
                } catch (ApiException $e) {
                    echo "Error reopening proposal: " . $e->getMessage() . "\n";
                    echo "Detailed error information:\n";
                    foreach ($e->getErrors() as $error) {
                        echo "- Error code: " . $error->getErrorString() . "\n";
                        echo "  At: " . $error->getFieldPath() . "\n";
                        echo "  Trigger: " . $error->getTrigger() . "\n";

                        if ($error->getErrorString() === 'ProposalActionError.NOT_APPLICABLE') {
                            echo "\nThis proposal cannot be reopened in its current state.\n";
                            echo "Current status: " . $proposal->getStatus() . "\n";
                            echo "Is Archived: " . ($proposal->getIsArchived() ? 'Yes' : 'No') . "\n";
                            echo "Is Sold: " . ($proposal->getIsSold() ? 'Yes' : 'No') . "\n";

                            // If proposal is approved and sold, we'll try to proceed with changes
                            if ($proposal->getStatus() === 'APPROVED' && $proposal->getIsSold()) {
                                echo "\nProposal is APPROVED and SOLD. Will attempt direct updates.\n";
                                break; // Exit the error loop and continue with changes
                            } else {
                                throw new Exception("Cannot proceed - proposal cannot be reopened and is not in a state for direct updates");
                            }
                        }
                    }
                    if ($proposal->getStatus() !== 'APPROVED' || !$proposal->getIsSold()) {
                        throw $e; // Only throw if we can't proceed with changes
                    }
                }
            } else {
                echo "\nProposal is already in DRAFT status - proceeding with changes\n";
            }

            // STEP 2: Make all necessary changes
            echo "\n=== STEP 2: Applying Changes ===\n";
            $changesApplied = false;

            try {
                // Debug current state of labels
                echo "\n=== STEP 2a: Label Matching Details ===\n";
                $currentLabels = $proposalLineItem->getAppliedLabels();
                $effectiveLabels = $proposalLineItem->getEffectiveAppliedLabels();

                // Debug input data for labels
                echo "\nDEBUG: CSV Input:\n";
                echo "- Label Names/IDs from CSV: " . ($lineItemData['appliedLabels'] ?? 'none') . "\n";
                echo "- Label Operation: " . ($lineItemData['labelOperation'] ?? 'none') . "\n";

                echo "\nDEBUG: Current Labels on Line Item:\n";
                echo "Applied Labels:\n";
                if ($currentLabels !== null) {
                    echo "- Number of applied labels: " . count($currentLabels) . "\n";
                    foreach ($currentLabels as $label) {
                        echo "- Applied Label ID: " . $label->getLabelId() . "\n";
                    }
                } else {
                    echo "- No applied labels found\n";
                }

                echo "\nEffective Labels:\n";
                if ($effectiveLabels !== null) {
                    echo "- Number of effective labels: " . count($effectiveLabels) . "\n";
                    foreach ($effectiveLabels as $label) {
                        echo "- Effective Label ID: " . $label->getLabelId() . "\n";
                    }
                } else {
                    echo "- No effective labels found\n";
                }

                // Modify the if statement to be more explicit
                if (isset($lineItemData['appliedLabels']) && $lineItemData['appliedLabels'] !== '') {
                    echo "\n=== Starting Label Update Process ===\n";
                    echo "DEBUG: Label Matching Process:\n";

                    // Parse the label IDs from CSV
                    $labelInput = trim($lineItemData['appliedLabels']);

                    // Initialize array for label IDs
                    $targetLabelIds = [];

                    if (is_numeric($labelInput)) {
                        // If it's already an ID, use it directly
                        echo "Debug: Using provided label ID directly: $labelInput\n";
                        $targetLabelIds[] = intval($labelInput);
                    } else {
                        // If it's a name, look up the ID
                        $labelNames = array_map('trim', explode(';', $labelInput));
                        echo "Debug: Looking up IDs for label names: " . implode(", ", $labelNames) . "\n";
                        $targetLabelIds = getLabelIdsByNames($serviceFactory, $session, $labelNames);
                    }

                    echo "Debug: Final label IDs to process: " . implode(", ", $targetLabelIds) . "\n";

                    // Get the operation type
                    $operation = $lineItemData['labelOperation'] ?? 'ADD';
                    echo "Operation type: $operation\n";

                    if ($operation === 'REMOVE') {
                        echo "\nProcessing REMOVE operation...\n";

                        // Get existing labels
                        $existingLabels = $proposalLineItem->getAppliedLabels() ?: [];
                        $effectiveLabels = $proposalLineItem->getEffectiveAppliedLabels() ?: [];

                        echo "Current state:\n";
                        echo "- Applied labels: " . count($existingLabels) . "\n";
                        echo "- Effective labels: " . count($effectiveLabels) . "\n";

                        // Use updateLabels function for consistent label handling
                        $updatedLabels = updateLabels($existingLabels, $targetLabelIds, 'REMOVE');

                        if (empty($updatedLabels)) {
                            $proposalLineItem->setAppliedLabels(null);
                            echo "\n=== DEBUG: Label State After Removal ===\n";
                            echo "Set labels to NULL\n";
                        } else {
                            $proposalLineItem->setAppliedLabels($updatedLabels);
                            echo "\n=== DEBUG: Label State After Update ===\n";
                            echo "Set " . count($updatedLabels) . " labels\n";
                        }

                        $finalLabels = $proposalLineItem->getAppliedLabels();
                        echo "Current appliedLabels property value: " . ($finalLabels === null ? "NULL" : count($finalLabels) . " labels") . "\n";
                        if ($finalLabels !== null) {
                            echo "Labels present:\n";
                            foreach ($finalLabels as $label) {
                                echo "- Label ID: " . $label->getLabelId() . "\n";
                            }
                        }

                        $changesApplied = true;
                    } else {
                        // For ADD operation, use the already processed label IDs
                        echo "\nProcessing ADD operation...\n";

                        // Get existing labels
                        $existingLabels = $proposalLineItem->getAppliedLabels() ?: [];

                        // Update labels using the already processed targetLabelIds
                        $updatedLabels = updateLabels($existingLabels, $targetLabelIds, $operation);

                        if (empty($updatedLabels)) {
                            // Explicitly set null if no labels remain
                            $proposalLineItem->setAppliedLabels(null);
                            echo "\n=== DEBUG: Label State After Removal ===\n";
                            echo "Set labels to NULL\n";
                            $finalLabels = $proposalLineItem->getAppliedLabels();
                            echo "Current appliedLabels property value: " . ($finalLabels === null ? "NULL" : count($finalLabels) . " labels") . "\n";
                            if ($finalLabels !== null && !empty($finalLabels)) {
                                echo "WARNING: Labels still present after attempted removal:\n";
                                foreach ($finalLabels as $label) {
                                    echo "- Label ID: " . $label->getLabelId() . "\n";
                                }
                            }
                        } else {
                            // Set the array of remaining labels if there are any
                            $proposalLineItem->setAppliedLabels($updatedLabels);
                            echo "\n=== DEBUG: Label State After Update ===\n";
                            echo "Set " . count($updatedLabels) . " labels\n";
                            $finalLabels = $proposalLineItem->getAppliedLabels();
                            echo "Current appliedLabels property value: " . ($finalLabels === null ? "NULL" : count($finalLabels) . " labels") . "\n";
                            if ($finalLabels !== null) {
                                echo "Labels present:\n";
                                foreach ($finalLabels as $label) {
                                    echo "- Label ID: " . $label->getLabelId() . "\n";
                                }
                            }
                        }

                        $changesApplied = true;
                    }
                }

                // Update delivery rate type if specified
                if (!empty($lineItemData['deliveryRateType'])) {
                    echo "\nUpdating delivery rate type to: " . $lineItemData['deliveryRateType'] . "\n";
                    $proposalLineItem->setDeliveryRateType($lineItemData['deliveryRateType']);
                    $changesApplied = true;
                }

                // Update ad unit targeting if specified
                if (!empty($lineItemData['adUnitIds'])) {
                    echo "\nUpdating ad unit targeting...\n";
                    $adUnitIds = array_map('trim', explode(';', $lineItemData['adUnitIds']));
                    $targeting = $proposalLineItem->getTargeting() ?? new Targeting();
                    $inventoryTargeting = $targeting->getInventoryTargeting() ?? new InventoryTargeting();
                    $existingAdUnits = $inventoryTargeting->getTargetedAdUnits() ?? [];
                    $operation = $lineItemData['adUnitOperation'] ?? 'ADD';

                    echo "Ad unit operation: " . $operation . "\n";
                    echo "Found " . count($existingAdUnits) . " existing ad units\n";
                    echo "Processing " . count($adUnitIds) . " ad units from CSV\n";

                    if ($operation === 'ADD') {
                        echo "Adding new ad units...\n";
                        foreach ($adUnitIds as $adUnitId) {
                            $found = false;
                            foreach ($existingAdUnits as $existingAdUnit) {
                                if ($existingAdUnit->getAdUnitId() == $adUnitId) {
                                    $found = true;
                                    echo "Ad unit ID $adUnitId already exists - skipping\n";
                                    break;
                                }
                            }
                            if (!$found) {
                                $adUnitTargeting = new AdUnitTargeting();
                                $adUnitTargeting->setAdUnitId($adUnitId);
                                $adUnitTargeting->setIncludeDescendants(true);
                                $existingAdUnits[] = $adUnitTargeting;
                                echo "Added new ad unit ID: $adUnitId\n";
                            }
                        }
                        $inventoryTargeting->setTargetedAdUnits($existingAdUnits);
                    } elseif ($operation === 'REMOVE') {
                        echo "Removing specified ad units...\n";
                        $updatedAdUnits = [];
                        foreach ($existingAdUnits as $existingAdUnit) {
                            if (!in_array($existingAdUnit->getAdUnitId(), $adUnitIds)) {
                                $updatedAdUnits[] = $existingAdUnit;
                                echo "Kept ad unit ID: " . $existingAdUnit->getAdUnitId() . "\n";
                            } else {
                                echo "Removed ad unit ID: " . $existingAdUnit->getAdUnitId() . "\n";
                            }
                        }
                        $inventoryTargeting->setTargetedAdUnits($updatedAdUnits);
                    }

                    $targeting->setInventoryTargeting($inventoryTargeting);
                    $proposalLineItem->setTargeting($targeting);
                    $changesApplied = true;
                }

                // Update geo targeting if specified
                if (!empty($lineItemData['postcodes'])) {
                    echo "\nProcessing geo targeting...\n";
                    $targeting = $proposalLineItem->getTargeting() ?? new Targeting();
                    $currentGeoTargeting = $targeting->getGeoTargeting();
                    $operation = $lineItemData['postcodeOperation'] ?? 'ADD';

                    // Get existing locations
                    $existingLocations = [];
                    if ($currentGeoTargeting !== null) {
                        $existingLocations = $currentGeoTargeting->getTargetedLocations() ?? [];
                    }

                    echo "Current geo targeting has " . count($existingLocations) . " locations\n";

                    $postcodes = array_map('trim', explode(';', $lineItemData['postcodes']));
                    $newLocations = [];

                    if ($operation === 'REMOVE') {
                        echo "Removing specified postcodes...\n";
                        // Keep locations that don't match the postcodes to remove
                        foreach ($existingLocations as $location) {
                            if (!in_array($location->getDisplayName(), $postcodes)) {
                                $newLocations[] = $location;
                                echo "Keeping location: " . $location->getDisplayName() . "\n";
                            } else {
                                echo "Removing location: " . $location->getDisplayName() . "\n";
                            }
                        }
                    } else {
                        // ADD operation
                        echo "Adding new postcodes...\n";
                        // Keep existing locations
                        $newLocations = $existingLocations;
                        $existingPostcodes = array_map(function ($loc) {
                            return $loc->getDisplayName();
                        }, $existingLocations);

                        // Add new postcodes
                        foreach ($postcodes as $postcode) {
                            if (!in_array($postcode, $existingPostcodes)) {
                                $location = getLocationIdForPostcode($postcode);
                                if ($location !== null) {
                                    $newLocations[] = $location;
                                    echo "Added new location: $postcode\n";
                                }
                            } else {
                                echo "Postcode $postcode already exists in targeting\n";
                            }
                        }
                    }

                    // Update targeting with new locations
                    $geoTargeting = new GeoTargeting();
                    $geoTargeting->setTargetedLocations($newLocations);
                    $targeting->setGeoTargeting($geoTargeting);
                    $proposalLineItem->setTargeting($targeting);
                    $changesApplied = true;
                    echo "Updated geo targeting with " . count($newLocations) . " locations\n";
                }

                // Handle audience segment changes if specified
                if (!empty($lineItemData['audienceSegmentName'])) {
                    echo "\nProcessing audience segment changes...\n";
                    $operation = $lineItemData['audienceOperation'] ?? 'ADD';
                    echo "Operation: $operation\n";

                    // Get existing targeting object
                    $targeting = $proposalLineItem->getTargeting();
                    if ($targeting === null) {
                        $targeting = new Targeting();
                        // Set required request platform targeting
                        $requestPlatformTargeting = new RequestPlatformTargeting();
                        $requestPlatformTargeting->setTargetedRequestPlatforms([RequestPlatform::BROWSER]);
                        $targeting->setRequestPlatformTargeting($requestPlatformTargeting);
                    }

                    echo "\n=== STEP 1: CURRENT STATE ===\n";
                    echo "Current targeting object type: " . get_class($targeting) . "\n";
                    $currentCustomTargeting = $targeting->getCustomTargeting();
                    echo "Current custom targeting: " . ($currentCustomTargeting === null ? "NULL" : "EXISTS") . "\n";
                    if ($currentCustomTargeting !== null) {
                        printCustomTargetingDetails($currentCustomTargeting);
                    }

                    // Look up the segment ID first
                    $audienceSegmentService = $serviceFactory->createAudienceSegmentService($session);
                    $statementBuilder = new StatementBuilder();
                    $statementBuilder->Where('name = :name')
                        ->WithBindVariableValue('name', $lineItemData['audienceSegmentName']);

                    $result = $audienceSegmentService->getAudienceSegmentsByStatement($statementBuilder->toStatement());

                    if ($result->getResults() === null || empty($result->getResults())) {
                        throw new Exception("Could not find audience segment ID for name: " . $lineItemData['audienceSegmentName']);
                    }

                    $segment = $result->getResults()[0];
                    $segmentId = $segment->getId();
                    echo "\n=== STEP 2: FOUND SEGMENT ===\n";
                    echo "Segment ID: " . $segmentId . "\n";
                    echo "Segment Name: " . $segment->getName() . "\n";

                    if ($operation === 'REMOVE') {
                        echo "\n=== STEP 3: REMOVING AUDIENCE SEGMENT ===\n";
                        if ($currentCustomTargeting === null) {
                            echo "No existing custom targeting found to remove from\n";
                        } else {
                            $updatedOrChildren = [];
                            $existingOrChildren = $currentCustomTargeting->getChildren() ?? [];

                            foreach ($existingOrChildren as $orChild) {
                                if ($orChild instanceof CustomCriteriaSet) {
                                    $updatedAndChildren = [];
                                    $andChildren = $orChild->getChildren() ?? [];

                                    foreach ($andChildren as $andChild) {
                                        if ($andChild instanceof AudienceSegmentCriteria) {
                                            $segmentIds = $andChild->getAudienceSegmentIds();
                                            if (!in_array($segmentId, $segmentIds)) {
                                                $updatedAndChildren[] = $andChild;
                                            } else {
                                                echo "Removing audience segment $segmentId from AND set\n";
                                            }
                                        } else {
                                            $updatedAndChildren[] = $andChild;
                                        }
                                    }

                                    if (!empty($updatedAndChildren)) {
                                        $orChild->setChildren($updatedAndChildren);
                                        $updatedOrChildren[] = $orChild;
                                    }
                                } else {
                                    $updatedOrChildren[] = $orChild;
                                }
                            }

                            if (empty($updatedOrChildren)) {
                                $targeting->setCustomTargeting(null);
                                echo "All custom targeting removed as no criteria remain\n";
                            } else {
                                $currentCustomTargeting->setChildren($updatedOrChildren);
                                $targeting->setCustomTargeting($currentCustomTargeting);
                                echo "Updated custom targeting with audience segment removed\n";
                            }
                        }
                    } else {
                        echo "\n=== STEP 3: ADDING AUDIENCE SEGMENT ===\n";
                        // Create new audience segment criteria
                        echo "Creating AudienceSegmentCriteria...\n";
                        $audienceCriteria = new AudienceSegmentCriteria();
                        $audienceCriteria->setOperator(CustomCriteriaComparisonOperator::IS);
                        $audienceCriteria->setAudienceSegmentIds([$segmentId]);

                        // Get existing custom targeting
                        $existingCustomTargeting = $targeting->getCustomTargeting();

                        if ($existingCustomTargeting === null || !($existingCustomTargeting instanceof CustomCriteriaSet)) {
                            echo "No existing custom targeting, creating new structure...\n";
                            // Create a new AND set with just the audience segment
                            $andSet = new CustomCriteriaSet();
                            $andSet->setLogicalOperator(CustomCriteriaSetLogicalOperator::AND_VALUE);
                            $andSet->setChildren([$audienceCriteria]);

                            // Wrap it in an OR set as the top level
                            $orSet = new CustomCriteriaSet();
                            $orSet->setLogicalOperator(CustomCriteriaSetLogicalOperator::OR_VALUE);
                            $orSet->setChildren([$andSet]);
                        } else {
                            echo "Found existing custom targeting, preserving and adding new criteria...\n";
                            // Get the existing children from the top-level OR set
                            $existingOrChildren = $existingCustomTargeting->getChildren() ?? [];
                            $updatedOrChildren = [];

                            foreach ($existingOrChildren as $orChild) {
                                if ($orChild instanceof CustomCriteriaSet) {
                                    // If this child is an AND set, add the audience segment to it
                                    if ($orChild->getLogicalOperator() === CustomCriteriaSetLogicalOperator::AND_VALUE) {
                                        $existingAndChildren = $orChild->getChildren() ?? [];
                                        $existingAndChildren[] = $audienceCriteria;
                                        $orChild->setChildren($existingAndChildren);
                                        $updatedOrChildren[] = $orChild;
                                        echo "Added audience segment to existing AND set\n";
                                    }
                                    // If this child is another OR set, wrap it in an AND set with the audience segment
                                    else if ($orChild->getLogicalOperator() === CustomCriteriaSetLogicalOperator::OR_VALUE) {
                                        $newAndSet = new CustomCriteriaSet();
                                        $newAndSet->setLogicalOperator(CustomCriteriaSetLogicalOperator::AND_VALUE);
                                        $newAndSet->setChildren(array_merge($orChild->getChildren() ?? [], [$audienceCriteria]));
                                        $updatedOrChildren[] = $newAndSet;
                                        echo "Added audience segment to OR set by wrapping in AND\n";
                                    }
                                } else {
                                    // For any other type of child, create a new AND set with it and the audience segment
                                    $newAndSet = new CustomCriteriaSet();
                                    $newAndSet->setLogicalOperator(CustomCriteriaSetLogicalOperator::AND_VALUE);
                                    $newAndSet->setChildren([$orChild, $audienceCriteria]);
                                    $updatedOrChildren[] = $newAndSet;
                                    echo "Created new AND set with existing criteria and audience segment\n";
                                }
                            }

                            // Create the top-level OR set with all the updated children
                            $orSet = new CustomCriteriaSet();
                            $orSet->setLogicalOperator(CustomCriteriaSetLogicalOperator::OR_VALUE);
                            $orSet->setChildren($updatedOrChildren);
                        }

                        // Preserve existing targeting settings
                        if ($targeting->getGeoTargeting() === null) {
                            $targeting->setGeoTargeting(new GeoTargeting());
                        }
                        if ($targeting->getInventoryTargeting() === null) {
                            $targeting->setInventoryTargeting(new InventoryTargeting());
                        }
                        if ($targeting->getRequestPlatformTargeting() === null) {
                            $requestPlatformTargeting = new RequestPlatformTargeting();
                            $requestPlatformTargeting->setTargetedRequestPlatforms([RequestPlatform::VIDEO_PLAYER]);
                            $targeting->setRequestPlatformTargeting($requestPlatformTargeting);
                        }

                        // Set the custom targeting
                        echo "\nSetting custom targeting...\n";
                        $targeting->setCustomTargeting($orSet);

                        echo "\n=== VERIFICATION OF TARGETING STRUCTURE ===\n";
                        $customTargeting = $targeting->getCustomTargeting();
                        if ($customTargeting === null) {
                            throw new Exception("Custom targeting is NULL after setting");
                        }

                        echo "Top level structure:\n";
                        if ($customTargeting instanceof CustomCriteriaSet) {
                            echo "- Operator: " . $customTargeting->getLogicalOperator() . "\n";

                            $firstChildren = $customTargeting->getChildren();
                            if (!empty($firstChildren)) {
                                echo "\nFirst level children:\n";
                                echo "- Number of children: " . count($firstChildren) . "\n";
                                echo "- Child type: " . get_class($firstChildren[0]) . "\n";
                                if ($firstChildren[0] instanceof CustomCriteriaSet) {
                                    echo "- Child operator: " . $firstChildren[0]->getLogicalOperator() . "\n";

                                    $secondChildren = $firstChildren[0]->getChildren();
                                    if (!empty($secondChildren)) {
                                        echo "\nSecond level children:\n";
                                        echo "- Number of children: " . count($secondChildren) . "\n";
                                        echo "- Child type: " . get_class($secondChildren[0]) . "\n";
                                        if ($secondChildren[0] instanceof AudienceSegmentCriteria) {
                                            echo "- Segment IDs: " . implode(", ", $secondChildren[0]->getAudienceSegmentIds()) . "\n";
                                        }
                                    }
                                }
                            }
                        } else {
                            echo "Warning: Custom targeting is not a CustomCriteriaSet\n";
                        }

                        // Set targeting back to proposal line item
                        echo "\nSetting targeting on proposal line item...\n";
                        $proposalLineItem->setTargeting($targeting);
                        $changesApplied = true;
                    }
                }

                // Handle keyvalue removal (no operation needed, always REMOVE)
                if (!empty($lineItemData['keyValue'])) {
                    echo "\nProcessing keyvalue removal for: " . $lineItemData['keyValue'] . "\n";
                    $targeting = $proposalLineItem->getTargeting() ?? new Targeting();
                    $existingCustomTargeting = $targeting->getCustomTargeting();

                    if ($existingCustomTargeting !== null) {
                        echo "Found existing custom targeting - processing removal...\n";
                        echo "Current custom targeting structure:\n";
                        printCustomTargetingDetails([$existingCustomTargeting]);

                        // Special handling for "Audience segment"
                        if (strtolower($lineItemData['keyValue']) === 'audience segment') {
                            echo "\nHandling Audience Segment removal...\n";
                            $updatedOrChildren = [];
                            $existingOrChildren = $existingCustomTargeting->getChildren() ?? [];

                            foreach ($existingOrChildren as $orChild) {
                                if ($orChild instanceof CustomCriteriaSet) {
                                    $updatedAndChildren = [];
                                    $andChildren = $orChild->getChildren() ?? [];

                                    foreach ($andChildren as $andChild) {
                                        if (!($andChild instanceof AudienceSegmentCriteria)) {
                                            $updatedAndChildren[] = $andChild;
                                            echo "Keeping non-audience criteria: " . get_class($andChild) . "\n";
                                        } else {
                                            echo "Removing AudienceSegmentCriteria\n";
                                        }
                                    }

                                    if (!empty($updatedAndChildren)) {
                                        $orChild->setChildren($updatedAndChildren);
                                        $updatedOrChildren[] = $orChild;
                                    }
                                } else {
                                    $updatedOrChildren[] = $orChild;
                                }
                            }

                            if (empty($updatedOrChildren)) {
                                echo "All custom targeting removed as no criteria remain\n";
                                $targeting->setCustomTargeting(null);
                            } else {
                                echo "Setting updated custom targeting with audience segment removed\n";
                                $existingCustomTargeting->setChildren($updatedOrChildren);
                                $targeting->setCustomTargeting($existingCustomTargeting);
                            }

                            $proposalLineItem->setTargeting($targeting);
                            $changesApplied = true;
                        } else {
                            // Original keyvalue removal logic for non-audience segment cases
                            // Create a new top-level OR set
                            $updatedSet = new CustomCriteriaSet();
                            $updatedSet->setLogicalOperator(CustomCriteriaSetLogicalOperator::OR_VALUE);
                            $updatedChildren = [];

                            // Get keyId for the specified keyvalue
                            $keyId = null;
                            try {
                                echo "\nAttempting to get key ID for: " . $lineItemData['keyValue'] . "\n";
                                $customTargetingService = $serviceFactory->createCustomTargetingService($session);

                                // Try different variations of the key name
                                $keyVariations = [
                                    strtolower($lineItemData['keyValue']),
                                    strtoupper($lineItemData['keyValue']),
                                    ucfirst(strtolower($lineItemData['keyValue'])),
                                    str_replace(' ', '', $lineItemData['keyValue'])
                                ];

                                echo "Trying key variations: " . implode(", ", $keyVariations) . "\n";

                                foreach ($keyVariations as $keyVariation) {
                                    try {
                                        $statementBuilder = new StatementBuilder();
                                        $query = 'name = :name';
                                        $statementBuilder->Where($query)
                                            ->WithBindVariableValue('name', $keyVariation);

                                        echo "Debug: Trying query: " . $statementBuilder->toStatement()->getQuery() . "\n";
                                        echo "Debug: With value: " . $keyVariation . "\n";

                                        $result = $customTargetingService->getCustomTargetingKeysByStatement($statementBuilder->toStatement());

                                        if ($result !== null && $result->getResults() !== null && !empty($result->getResults())) {
                                            $keyId = $result->getResults()[0]->getId();
                                            echo "Found key ID: " . $keyId . " for variation: " . $keyVariation . "\n";
                                            break;
                                        }
                                    } catch (ApiException $e) {
                                        echo "API Exception for variation '$keyVariation':\n";
                                        foreach ($e->getErrors() as $error) {
                                            echo "Error: " . $error->getErrorString() . "\n";
                                            echo "Field Path: " . $error->getFieldPath() . "\n";
                                            echo "Trigger: " . $error->getTrigger() . "\n";
                                        }
                                        continue;
                                    }
                                }

                                if (!isset($keyId)) {
                                    throw new Exception("Could not find key ID for any variation of: " . $lineItemData['keyValue']);
                                }
                            } catch (ApiException $e) {
                                echo "API Exception while getting key ID:\n";
                                foreach ($e->getErrors() as $error) {
                                    echo "Error: " . $error->getErrorString() . "\n";
                                    echo "Field Path: " . $error->getFieldPath() . "\n";
                                    echo "Trigger: " . $error->getTrigger() . "\n";
                                }
                                throw $e;
                            } catch (Exception $e) {
                                echo "Exception while getting key ID: " . $e->getMessage() . "\n";
                                throw $e;
                            }

                            // Process each child in the existing targeting
                            $existingChildren = $existingCustomTargeting->getChildren() ?? [];
                            foreach ($existingChildren as $child) {
                                if ($child instanceof CustomCriteriaSet) {
                                    if ($child->getLogicalOperator() === CustomCriteriaSetLogicalOperator::AND_VALUE) {
                                        $updatedAndChildren = [];
                                        foreach ($child->getChildren() ?? [] as $andChild) {
                                            $keepChild = true;

                                            // Check if this is a CustomCriteria with matching keyId
                                            if ($andChild instanceof CustomCriteria && $andChild->getKeyId() == $keyId) {
                                                $keepChild = false;
                                                echo "Removing CustomCriteria with keyId $keyId from AND set\n";
                                            }

                                            if ($keepChild) {
                                                $updatedAndChildren[] = $andChild;
                                                echo "Keeping criteria in AND set: " . get_class($andChild) . "\n";
                                            }
                                        }

                                        if (!empty($updatedAndChildren)) {
                                            $newAndSet = new CustomCriteriaSet();
                                            $newAndSet->setLogicalOperator(CustomCriteriaSetLogicalOperator::AND_VALUE);
                                            $newAndSet->setChildren($updatedAndChildren);
                                            $updatedChildren[] = $newAndSet;
                                            echo "Added AND set with " . count($updatedAndChildren) . " children\n";
                                        }
                                    } else {
                                        // For OR sets, process recursively
                                        $updatedOrChildren = [];
                                        foreach ($child->getChildren() ?? [] as $orChild) {
                                            $keepChild = true;

                                            // Check if this is a CustomCriteria with matching keyId
                                            if ($orChild instanceof CustomCriteria && $orChild->getKeyId() == $keyId) {
                                                $keepChild = false;
                                                echo "Removing CustomCriteria with keyId $keyId from OR set\n";
                                            }

                                            if ($keepChild) {
                                                $updatedOrChildren[] = $orChild;
                                                echo "Keeping criteria in OR set: " . get_class($orChild) . "\n";
                                            }
                                        }
                                        if (!empty($updatedOrChildren)) {
                                            $newOrSet = new CustomCriteriaSet();
                                            $newOrSet->setLogicalOperator(CustomCriteriaSetLogicalOperator::OR_VALUE);
                                            $newOrSet->setChildren($updatedOrChildren);
                                            $updatedChildren[] = $newOrSet;
                                            echo "Added OR set with " . count($updatedOrChildren) . " children\n";
                                        }
                                    }
                                } else {
                                    $keepChild = true;

                                    // Check if this is a CustomCriteria with matching keyId at top level
                                    if ($child instanceof CustomCriteria && $child->getKeyId() == $keyId) {
                                        $keepChild = false;
                                        echo "Removing CustomCriteria with keyId $keyId from top level\n";
                                    }

                                    if ($keepChild) {
                                        $updatedChildren[] = $child;
                                        echo "Keeping criteria at top level: " . get_class($child) . "\n";
                                    }
                                }
                            }

                            if (empty($updatedChildren)) {
                                echo "All custom targeting removed as no criteria remain\n";
                                $targeting->setCustomTargeting(null);
                            } else {
                                echo "Setting updated custom targeting with criteria removed\n";
                                $updatedSet->setChildren($updatedChildren);
                                $targeting->setCustomTargeting($updatedSet);

                                echo "\nVerifying final targeting structure:\n";
                                printCustomTargetingDetails([$targeting->getCustomTargeting()]);
                            }

                            $proposalLineItem->setTargeting($targeting);
                            $changesApplied = true;
                        }
                    } else {
                        echo "No existing custom targeting found\n";
                    }
                }

                // Apply all changes
                if ($changesApplied) {
                    echo "\nApplying all changes to proposal line item...\n";
                    $result = $proposalLineItemService->updateProposalLineItems([$proposalLineItem]);
                    if ($result !== null && !empty($result)) {
                        echo "Successfully updated proposal line item with all changes\n";
                    }
                }
            } catch (ApiException $e) {
                echo "Error applying changes: " . $e->getMessage() . "\n";
                foreach ($e->getErrors() as $error) {
                    echo "- " . $error->getErrorString() . " @ " . $error->getFieldPath() . "\n";
                }
                throw $e;
            }

            // STEP 3: Finalize proposal if it was reopened
            if ($changesApplied) {
                echo "\n=== STEP 3: Finalizing Proposal ===\n";
                try {
                    // First sync with marketplace
                    echo "Step 3.1: Syncing with marketplace...\n";
                    $updateAction = new UpdateOrderWithSellerData();
                    $result = $proposalService->performProposalAction(
                        $updateAction,
                        $proposalBuilder->toStatement()
                    );

                    // Small delay to ensure sync is complete
                    sleep(2);

                    // Verify sync status
                    $proposalResult = $proposalService->getProposalsByStatement(
                        $proposalBuilder->toStatement()
                    );
                    $proposal = $proposalResult->getResults()[0];
                    echo "Status after sync: " . $proposal->getStatus() . "\n";

                    // Only proceed with buyer acceptance if the proposal is in DRAFT
                    if ($proposal->getStatus() === 'DRAFT') {
                        echo "Step 3.2: Requesting buyer acceptance...\n";
                        $requestAction = new RequestBuyerAcceptance();
                        $result = $proposalService->performProposalAction(
                            $requestAction,
                            $proposalBuilder->toStatement()
                        );

                        // Verify final status
                        sleep(2);
                        $proposalResult = $proposalService->getProposalsByStatement(
                            $proposalBuilder->toStatement()
                        );
                        $finalProposal = $proposalResult->getResults()[0];
                        echo "Final proposal status: " . $finalProposal->getStatus() . "\n";

                        if ($finalProposal->getStatus() === 'DRAFT') {
                            throw new Exception("Failed to finalize proposal - still in DRAFT state after all attempts");
                        }
                    } else if ($proposal->getStatus() === 'APPROVED' && $proposal->getIsSold()) {
                        echo "Proposal is already APPROVED and SOLD - no need for buyer acceptance\n";
                        echo "Changes have been applied successfully\n";
                    } else {
                        echo "Proposal is in " . $proposal->getStatus() . " state - cannot request buyer acceptance\n";
                        echo "Changes have been applied but may need manual review\n";
                    }
                } catch (ApiException $e) {
                    // Check if this is a NOT_APPLICABLE error for an APPROVED proposal
                    $isNotApplicableError = false;
                    foreach ($e->getErrors() as $error) {
                        if ($error->getErrorString() === 'ProposalActionError.NOT_APPLICABLE') {
                            $isNotApplicableError = true;
                            echo "Note: Proposal action not applicable - this is expected for APPROVED proposals\n";
                            break;
                        }
                    }

                    if (!$isNotApplicableError) {
                        echo "Error during finalization: " . $e->getMessage() . "\n";
                        foreach ($e->getErrors() as $error) {
                            echo "- " . $error->getErrorString() . " @ " . $error->getFieldPath() . "\n";
                        }
                        throw new Exception("Failed to finalize proposal due to API error");
                    }
                }
            } else {
                echo "\nSkipping finalization - no changes were applied\n";
            }

            echo "\n=== Proposal Processing Complete ===\n";
        } catch (Exception $e) {
            echo "Critical Error: " . get_class($e) . "\n";
            echo "Message: " . $e->getMessage() . "\n";
            echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
            global $lineNotUpdated;
            $lineNotUpdated[] = [$lineItemData['lineItemId'], $e->getMessage()];
        }
    }

    public static function main($lineItemData)
    {
        try {
            echo "Debug: Starting authentication process\n";
            echo "Debug: Looking for adsapi_php.ini in: " . realpath('./adsapi_php.ini') . "\n";

            if (!file_exists('./adsapi_php.ini')) {
                throw new Exception("adsapi_php.ini file not found in " . realpath('./'));
            }

            $oAuth2Credential = (new OAuth2TokenBuilder())
                ->fromFile('./adsapi_php.ini')
                ->build();
            echo "Debug: OAuth2 credential built successfully\n";

            $session = (new AdManagerSessionBuilder())
                ->fromFile('./adsapi_php.ini')
                ->withOAuth2Credential($oAuth2Credential)
                ->build();
            echo "Debug: Session built successfully\n";

            self::runExample(
                new ServiceFactory(),
                $session,
                $lineItemData
            );
        } catch (Exception $e) {
            echo "Critical Error in main(): " . get_class($e) . "\n";
            echo "Message: " . $e->getMessage() . "\n";
            echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
        }
    }
}

// Initialize error tracking
$lineNotUpdated = [];

// Process CSV file
$csvFile = 'prog_changes1.csv';
echo "Debug: Opening CSV file: " . realpath($csvFile) . "\n";

if (!file_exists($csvFile)) {
    die("Error: CSV file not found: $csvFile\n");
}

try {
    // Initialize services for mapping
    $oAuth2Credential = (new OAuth2TokenBuilder())
        ->fromFile('./adsapi_php.ini')
        ->build();
    echo "Debug: OAuth2 credential built successfully\n";

    $session = (new AdManagerSessionBuilder())
        ->fromFile('./adsapi_php.ini')
        ->withOAuth2Credential($oAuth2Credential)
        ->build();
    echo "Debug: Session built successfully\n";

    $serviceFactory = new ServiceFactory();

    // Add new function to check if ID is a proposal line item
    function isProposalLineItem($serviceFactory, $session, $lineItemId)
    {
        try {
            // First get the line item to get its proposal line item ID
            $lineItemService = $serviceFactory->createLineItemService($session);
            $statementBuilder = new StatementBuilder();
            $statementBuilder->Where('id = :id')
                ->WithBindVariableValue('id', $lineItemId);

            $result = $lineItemService->getLineItemsByStatement($statementBuilder->toStatement());

            if ($result->getResults() !== null && !empty($result->getResults())) {
                $lineItem = $result->getResults()[0];
                echo "Debug: Found line item, checking for proposal line item ID...\n";

                // Get the proposal line item using the line item ID
                $proposalLineItemService = $serviceFactory->createProposalLineItemService($session);
                $plStatementBuilder = new StatementBuilder();
                $plStatementBuilder->Where('lineItemId = :lineItemId')
                    ->WithBindVariableValue('lineItemId', $lineItemId);

                $plResult = $proposalLineItemService->getProposalLineItemsByStatement($plStatementBuilder->toStatement());

                if ($plResult->getResults() !== null && !empty($plResult->getResults())) {
                    $proposalLineItem = $plResult->getResults()[0];
                    echo "Debug: Found proposal line item ID: " . $proposalLineItem->getId() . "\n";
                    return true;
                }

                echo "Debug: No proposal line item found for this line item\n";
                return false;
            }
            return false;
        } catch (Exception $e) {
            error_log("Error checking proposal line item: " . $e->getMessage());
            return false;
        }
    }

    if (($handle = fopen($csvFile, 'r')) !== false) {
        echo "Debug: Successfully opened CSV file\n";

        // Skip header row
        fgetcsv($handle);
        echo "Debug: Skipped header row\n";

        $rowNumber = 1;
        // Process each line
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            try {
                echo "\nDebug: Processing row $rowNumber\n";
                echo "Debug: Raw row data (" . count($row) . " fields): " . implode(" | ", $row) . "\n";

                // Initialize processed data with default values
                $lineItemData = [
                    'lineItemId' => '',
                    'lineItemName' => '',
                    'appliedLabels' => '',
                    'labelOperation' => '',
                    'deliveryRateType' => '',
                    'adUnitIds' => '',
                    'adUnitOperation' => '',
                    'postcodes' => '',
                    'postcodeOperation' => '',
                    'keyValue' => '',
                    'audienceSegmentName' => '',
                    'audienceOperation' => ''
                ];

                // Map the data based on the actual CSV structure
                if (!empty($row[0])) {
                    $lineItemData['lineItemId'] = trim($row[0]);
                }
                if (!empty($row[1])) {
                    $lineItemData['lineItemName'] = trim($row[1]);
                }
                if (!empty($row[2])) {
                    $lineItemData['appliedLabels'] = trim($row[2]);
                }
                if (!empty($row[3])) {
                    // Convert operation to uppercase for consistency
                    $lineItemData['labelOperation'] = strtoupper(trim($row[3]));
                }
                if (!empty($row[4])) {
                    // Map ASAP to AS_FAST_AS_POSSIBLE
                    $pacing = strtoupper(trim($row[4]));
                    $lineItemData['deliveryRateType'] = $pacing === 'ASAP' ? 'AS_FAST_AS_POSSIBLE' : $pacing;
                }
                if (!empty($row[5])) {
                    $adUnitNames = array_map('trim', explode(',', $row[5]));
                    $lineItemData['adUnitIds'] = implode(';', $adUnitNames); // We'll map these to IDs later
                }
                if (!empty($row[6])) {
                    // Convert operation to uppercase for consistency
                    $lineItemData['adUnitOperation'] = strtoupper(trim($row[6]));
                }
                if (!empty($row[7])) {
                    $lineItemData['postcodes'] = trim($row[7]);
                }
                if (!empty($row[8])) {
                    // Convert operation to uppercase for consistency
                    $lineItemData['postcodeOperation'] = strtoupper(trim($row[8]));
                }
                if (!empty($row[9])) {
                    $lineItemData['keyValue'] = trim($row[9]);
                }
                if (!empty($row[10])) {
                    $lineItemData['audienceSegmentName'] = trim($row[10]);
                }
                if (!empty($row[11])) {
                    // Convert operation to uppercase for consistency
                    $lineItemData['audienceOperation'] = strtoupper(trim($row[11]));
                }

                echo "Debug: Processed data structure:\n";
                foreach ($lineItemData as $key => $value) {
                    echo "  $key: " . (empty($value) ? "(empty)" : $value) . "\n";
                }

                // Validate required fields
                if (empty($lineItemData['lineItemId'])) {
                    throw new Exception("Line item ID is required but was empty");
                }

                // Clean up the data
                $lineItemData['adUnitIds'] = str_replace(' ', '', $lineItemData['adUnitIds']);
                $lineItemData['postcodes'] = str_replace(' ', '', $lineItemData['postcodes']);

                // Map label names to IDs if needed
                if (!empty($lineItemData['appliedLabels'])) {
                    $labelInput = trim($lineItemData['appliedLabels']);
                    // Check if the input is numeric (an ID) or a name
                    if (is_numeric($labelInput)) {
                        // If it's already an ID, use it directly
                        echo "Debug: Using provided label ID directly: $labelInput\n";
                        $lineItemData['appliedLabels'] = $labelInput;
                    } else {
                        // If it's a name, look up the ID
                        $labelNames = array_map('trim', explode(';', $labelInput));
                        echo "Debug: Mapping label names: " . implode(", ", $labelNames) . "\n";
                        $labelIds = getLabelIdsByNames($serviceFactory, $session, $labelNames);
                        $lineItemData['appliedLabels'] = implode(';', $labelIds);
                    }
                    echo "Debug: Final label IDs to process: " . $lineItemData['appliedLabels'] . "\n";
                }

                // Map ad unit names to IDs if needed
                if (!empty($lineItemData['adUnitIds']) && !is_numeric(explode(';', $lineItemData['adUnitIds'])[0])) {
                    $adUnitNames = array_map('trim', explode(';', $lineItemData['adUnitIds']));
                    echo "Debug: Mapping ad unit names: " . implode(", ", $adUnitNames) . "\n";
                    $adUnitIds = getAdUnitIdsByNames($serviceFactory, $session, $adUnitNames);
                    $lineItemData['adUnitIds'] = implode(';', $adUnitIds);
                    echo "Debug: Final ad unit IDs to process: " . $lineItemData['adUnitIds'] . "\n";
                }

                try {
                    // First check if it's a proposal line item
                    echo "Debug: Checking if line item {$lineItemData['lineItemId']} is a programmatic line item...\n";
                    if (isProposalLineItem($serviceFactory, $session, $lineItemData['lineItemId'])) {
                        echo "Debug: This is a programmatic line item, using proposal workflow\n";
                        UpdateProgrammaticLineItems::main($lineItemData);
                    } else {
                        echo "Debug: This is a regular line item\n";
                        UpdateLineItems::main($lineItemData);
                    }
                } catch (Exception $e) {
                    echo "Error processing line: " . $e->getMessage() . "\n";
                    $lineNotUpdated[] = [$lineItemData['lineItemId'], $e->getMessage()];
                }
            } catch (Exception $e) {
                echo "Error processing row $rowNumber: " . $e->getMessage() . "\n";
                if (isset($lineItemData) && !empty($lineItemData['lineItemId'])) {
                    $lineNotUpdated[] = [$lineItemData['lineItemId'], $e->getMessage()];
                } else {
                    $lineNotUpdated[] = ["Row $rowNumber", $e->getMessage()];
                }
            }
        }
        fclose($handle);
    } else {
        throw new Exception("Failed to open CSV file");
    }
} catch (Exception $e) {
    echo "Critical Error in main script: " . get_class($e) . "\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

// Write errors to CSV
try {
    $errorFile = fopen('linesnotupdated.csv', 'w');
    fputcsv($errorFile, ['Line Item ID', 'Error Message']);
    foreach ($lineNotUpdated as $row) {
        fputcsv($errorFile, $row);
    }
    fclose($errorFile);
} catch (Exception $e) {
    echo "Error writing to error file: " . $e->getMessage() . "\n";
}

function verifyTargetingStructure($targeting)
{
    $customTargeting = $targeting->getCustomTargeting();
    if ($customTargeting === null || !is_array($customTargeting) || count($customTargeting) !== 1) {
        echo "ERROR: Top level custom targeting should be an array with exactly one element\n";
        return false;
    }

    $topLevelSet = $customTargeting[0];
    if (
        !($topLevelSet instanceof CustomCriteriaSet) ||
        $topLevelSet->getLogicalOperator() !== 'OR' ||
        !is_array($topLevelSet->getChildren()) ||
        count($topLevelSet->getChildren()) !== 1
    ) {
        echo "ERROR: Top level set should be OR with exactly one child\n";
        return false;
    }

    $nestedSet = $topLevelSet->getChildren()[0];
    if (
        !($nestedSet instanceof CustomCriteriaSet) ||
        $nestedSet->getLogicalOperator() !== 'AND' ||
        !is_array($nestedSet->getChildren()) ||
        count($nestedSet->getChildren()) !== 1
    ) {
        echo "ERROR: Nested set should be AND with exactly one child\n";
        return false;
    }

    $criteria = $nestedSet->getChildren()[0];
    if (
        !($criteria instanceof AudienceSegmentCriteria) ||
        $criteria->getOperator() !== 'IS' ||
        !is_array($criteria->getAudienceSegmentIds()) ||
        count($criteria->getAudienceSegmentIds()) !== 1
    ) {
        echo "ERROR: Criteria should be AudienceSegmentCriteria with IS operator and one segment ID\n";
        return false;
    }

    return true;
}
