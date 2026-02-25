# Testing Approach for GAM Bulk Update Tool

This document outlines pseudocode examples for different testing layers (Feature, Unit, Integration) for the Google Ad Manager Bulk Update Tool.

## 1. Feature Tests (End-to-End Flow Simulation)

*Purpose: Test complete user flows through HTTP requests, ensuring controllers, routing, middleware, and basic service interactions work correctly. External APIs (like GAM) and potentially time-consuming Jobs are usually mocked.*

**Example: CSV Upload and Bulk Update Flow**

```pseudocode
Feature: User can upload a CSV and trigger a bulk update

Scenario: Valid CSV upload initiates a background job

SETUP:
  - Authenticate as a standard user (`actingAs(user)`)
  - Prepare a valid sample CSV file (`valid_upload.csv`)
  - Mock the Queue facade (`Queue::fake()`) to prevent real job dispatch
  - Mock the `GoogleAdManagerService` if needed for any initial data fetches by the controller (less common here)

TEST STEPS:
  // 1. Upload CSV
  - Make POST request to `/line-items/upload` endpoint with `valid_upload.csv`
  - Assert HTTP response is OK (200)
  - Assert JSON response contains 'status: success' and expected 'headers'/'data' structure

  // 2. Simulate User Confirmation & Trigger Update (assuming data/sessionId is passed)
  - Prepare valid bulk update data array (`update_payload`) based on parsed CSV data
  - Make POST request to `/line-items/bulkUpdate` with `update_payload`
  - Assert HTTP response is OK (200) or Accepted (202)
  - Assert JSON response indicates success ('status: success', 'message: Update queued...')

  // 3. Assert Job Was Pushed
  - Assert a specific Job (e.g., `ProcessBulkUpdate::class`) was pushed onto the queue (`Queue::assertPushed()`)
  - Optional: Assert the job was pushed with the correct data (`batch_id`, user ID, update payload)

CLEANUP:
  - (Laravel handles database transactions for tests automatically if configured)
```

## 2. Unit Tests (Class/Method Isolation)

*Purpose: Test individual classes or methods in isolation. All external dependencies (other services, models, facades, external APIs) are mocked to test only the logic within the unit.*

**Example 1: CsvService Validation Logic**

```pseudocode
Unit Test: CsvService::validateAndParseCsv

Test: Throws exception for invalid file type

SETUP:
  - Create an instance of `CsvService`
  - Create a mock file object representing a non-CSV file (e.g., image.jpg)

TEST STEPS:
  - EXPECT Exception (`InvalidFileTypeException::class` or similar)
  - CALL `$csvService->validateAndParseCsv(mock_non_csv_file)`

Test: Correctly parses valid CSV data

SETUP:
  - Create an instance of `CsvService`
  - Create a mock file object representing a valid CSV with known content (`mock_valid_csv_file`)
  - Define expected parsed array structure (`expected_array`)

TEST STEPS:
  - CALL `$parsedData = $csvService->validateAndParseCsv(mock_valid_csv_file)`
  - ASSERT `$parsedData` is equal to `expected_array`
```

**Example 2: GoogleAdManagerService Method (Hypothetical)**

```pseudocode
Unit Test: GoogleAdManagerService::buildUpdateStatement

Test: Correctly formats update data into GAM Statement object

SETUP:
  // Mock the dependencies of GoogleAdManagerService (e.g., GAM API Client Library objects)
  - Mock `AdManagerSession`
  - Mock `LineItemServiceInterface` from GAM Library
  - Create instance of `GoogleAdManagerService` injecting mocks

  - Define input `$updateData` array (e.g., ['line_item_id' => 123, 'budget' => 500.50])
  - Define expected structure/values of the GAM `Statement` object (`expected_statement`)

TEST STEPS:
  - CALL `$actualStatement = $googleAdManagerService->buildUpdateStatement($updateData)` // Assuming such a helper method exists
  - ASSERT `$actualStatement` has the correct properties and values matching `expected_statement`
  - ASSERT relevant methods were called on mocked GAM Library objects if necessary
```

**Example 3: ProcessBulkUpdate Job Handle Method**

```pseudocode
Unit Test: ProcessBulkUpdate::handle

Test: Iterates items, calls service, saves rollback, logs success

SETUP:
  - Mock `GoogleAdManagerService` (`mockGamService`)
  - Mock `Rollback` model (`mockRollbackModel`)
  - Mock `Log` model (`mockLogModel`)
  - Mock `Auth` facade to simulate logged-in user

  - Define input `$jobData` (user ID, batch ID, array of line item updates)
  - Define sample `$previousLineItemData` that `mockGamService` should return on 'fetch'
  - Instantiate the Job: `$job = new ProcessBulkUpdate($jobData)`

  // Configure Mocks:
  - Expect `mockGamService->fetchLineItem(item['line_item_id'])` to be called for each item, returning `$previousLineItemData`
  - Expect `mockRollbackModel::create([...])` to be called for each item with correct `batch_id`, `line_item_id`, `previous_data`
  - Expect `mockGamService->updateLineItem(item_data)` to be called for each item // Or updateLineItems if batching
  - Expect `mockLogModel::create([...])` to be called for each item with 'success' status

TEST STEPS:
  - CALL `$job->handle(mockGamService, mockRollbackModel, mockLogModel)` // Inject mocks if handle method uses DI, otherwise set them up before calling

  // ASSERTIONS:
  - Verify all expected methods were called on the mocks the correct number of times with the expected arguments.
```

## 3. Integration Tests (Component Interaction)

*Purpose: Test the interaction between a few closely related components, possibly hitting a real test database but still mocking external services like the GAM API.*

**Example: LineItemController interacting with CsvService**

```pseudocode
Integration Test: LineItemController Upload Endpoint

Test: Controller correctly uses CsvService and returns parsed data on valid upload

SETUP:
  - Use Laravel's testing traits (`RefreshDatabase`) to have a clean test database
  - Authenticate as a user (`actingAs(user)`)
  - Create a mock CSV file (`valid_csv_file`)
  - **Optional (If CsvService has its own dependencies):** Partially mock `CsvService` or ensure its dependencies are available/mocked if they touch external systems. If it's self-contained, no service mocking needed.

TEST STEPS:
  - Make POST request to `/line-items/upload` with `valid_csv_file`
  - Assert HTTP response is OK (200)
  - Assert JSON response structure matches expected output for parsed data (`status: success`, correct `headers`, correct `data` based on `valid_csv_file`)
  - Assert any expected database interactions initiated directly by the *controller* (if any) occurred (e.g., maybe logging the upload action itself).
```

**Example: Update Job interacting with Real Database for Rollback/Log**

```pseudocode
Integration Test: ProcessBulkUpdate Job Database Interaction

Test: Job correctly saves Rollback and Log records to the database

SETUP:
  - Use `RefreshDatabase` trait
  - Create a user record
  - Mock `GoogleAdManagerService` (`mockGamService`) - **Crucially, we mock the external API.**
  - Define input `$jobData` (user ID, batch ID, array of line item updates)
  - Define sample `$previousLineItemData` to be returned by the mocked service

  - Configure `mockGamService->fetchLineItem(...)` to return `$previousLineItemData`
  - Configure `mockGamService->updateLineItem(...)` to simulate success (return true or void)

  - Instantiate the Job: `$job = new ProcessBulkUpdate($jobData)`

TEST STEPS:
  - CALL `$job->handle(mockGamService)` // Assuming Log/Rollback models are used directly via Eloquent inside handle

  // ASSERTIONS (Database):
  - For each item in `$jobData['line_items']`:
    - Assert a `Rollback` record exists in the database with the correct `batch_id`, `line_item_id`, and `previous_data`.
    - Assert a `Log` record exists in the database with the correct `batch_id`, `line_item_id`, `user_id`, and 'success' status.
``` 