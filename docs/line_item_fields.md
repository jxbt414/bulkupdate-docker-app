# Google Ad Manager Line Item Modifiable Fields

## Basic Fields

- `name` (string) - The name of the line item
- `status` (string) - Status of the line item. Values:
    - DRAFT
    - PAUSED
    - READY
    - DELIVERING
    - COMPLETED
    - INACTIVE
    - ARCHIVED

## Budget and Delivery

- `budget` (Money)
    - `currencyCode` (string)
    - `microAmount` (long)
- `startDateTime` (DateTime)
- `endDateTime` (DateTime)
- `unlimitedEndDateTime` (boolean)
- `deliveryRateType` (string)
    - EVENLY
    - FRONTLOADED
    - AS_FAST_AS_POSSIBLE
- `priority` (string) - Priority from 1 to 16
- `primaryGoal` (Goal)
    - `goalType` (string)
        - LIFETIME
        - DAILY
        - NONE
    - `unitType` (string)
        - IMPRESSIONS
        - CLICKS
        - VIEWABLE_IMPRESSIONS
        - AUDIENCE_SEGMENT_VIEWS
    - `units` (long)

## Targeting

- `targeting` (object)
    - `geoTargeting`
        - `targetedLocations`
        - `excludedLocations`
    - `inventoryTargeting`
        - `targetedAdUnits`
        - `excludedAdUnits`
    - `dayPartTargeting`
        - `dayParts`
        - `timeZone`
    - `userDomainTargeting`
        - `domains`
        - `targeted` (boolean)
    - `technologyTargeting`
        - `browserTargeting`
        - `deviceCategoryTargeting`
        - `deviceCapabilityTargeting`
        - `deviceManufacturerTargeting`
        - `mobileCarrierTargeting`
        - `operatingSystemTargeting`
    - `customTargeting`
        - `keyValueTargetingExpression`

## Creative Settings

- `creativePlaceholders` (array)
    - `size` (Size)
        - `width` (int)
        - `height` (int)
    - `creativeTemplateId` (long)
- `allowOverbook` (boolean)
- `skipCrossSellingRuleWarnings` (boolean)
- `reserveAtCreation` (boolean)
- `stats` (Stats) - Read-only statistics
- `deliveryIndicator` (DeliveryIndicator) - Read-only delivery data
- `deliveryData` (DeliveryData) - Read-only delivery data

## Cost Settings

- `costType` (string)
    - CPC
    - CPM
    - CPD
    - CPU
- `costPerUnit` (Money)
    - `currencyCode` (string)
    - `microAmount` (long)
- `valueCostPerUnit` (Money)
    - `currencyCode` (string)
    - `microAmount` (long)

## Frequency Caps

- `frequencyCaps` (array)
    - `maxImpressions` (int)
    - `numTimeUnits` (int)
    - `timeUnit` (string)
        - MINUTE
        - HOUR
        - DAY
        - WEEK
        - MONTH
        - LIFETIME

## Labels and Notes

- `labels` (array of Label)
    - `id` (long)
    - `name` (string)
    - `description` (string)
- `comments` (string)
- `notes` (string)

## Recommended Fields for Initial Implementation

For our bulk update tool, I recommend we start with these most commonly used fields:

### Phase 1 (Current)

- `name` - Line item name
- `priority` - Priority level
- `budget` - Budget amount and currency
- `primaryGoal` - Impression goals

### Phase 2 (Next)

- `status` - Line item status
- `startDateTime` - Start date/time
- `endDateTime` - End date/time
- `deliveryRateType` - Delivery pacing
- `targeting` - Basic targeting options
- `labels` - Line item labels

### Phase 3 (Future)

- `frequencyCaps` - Frequency capping settings
- `creativePlaceholders` - Creative size settings
- `costType` and `costPerUnit` - Cost settings
- Advanced targeting options

## Notes

1. Some fields are read-only and cannot be modified:

    - `id`
    - `stats`
    - `deliveryIndicator`
    - `deliveryData`

2. Some fields require special handling:

    - Budget needs to be converted to microAmount (multiply by 1,000,000)
    - Dates need to be properly formatted
    - Targeting requires complex nested objects

3. Field dependencies:

    - `endDateTime` is ignored if `unlimitedEndDateTime` is true
    - `costPerUnit` is required if `costType` is set
    - `primaryGoal` requires both `unitType` and `units`

4. Line Item Type Restrictions:

    - Priority values are restricted by line item type:
        - Sponsorship: 1-4 only
        - Standard: 6-10 only
        - Network: 12 only
        - Bulk: 12 only
        - Price Priority: 12 only
        - Ad Exchange: 12 only
        - House: 16 only
        - Bumper: 16 only

5. Impression Goal Restrictions:

    - Sponsorship line items:
        - Can only use percentage-based goals (0-100%)
        - Must use PERCENTAGE unitType
    - Standard line items:
        - Can specify exact number of impressions
        - Uses IMPRESSIONS unitType

6. Programmatic Line Items:

    - Require special handling as they are linked to proposals
    - Process flow:
        1. Open/reopen the proposal first
        2. Make changes to the proposal line item
        3. Finalize/submit the proposal
        4. Some proposals require buyer acceptance before changes take effect
    - Cannot directly modify programmatic line items without going through the proposal workflow
    - Changes might be pending until buyer approves (for certain proposal types)
    - Sometimes there are unreserved lines which can only be updated if the start date is set to the current datetime

7. Implementation Considerations:
    - Need to validate priority values against line item type before update
    - Need to validate impression goals format (percentage vs absolute) based on line item type
    - Need to check if line item is programmatic before attempting direct updates
    - Should implement proposal workflow handling for programmatic line items
    - Should handle buyer acceptance requirements and status tracking for programmatic changes
    - Should implement line item targeting checks after bulk updates to check if any failed or were missed
    - Only exception is lines requiring buyer acceptance, as the line item won't have the update until buyer approves
