**Project Requirements Document (PRD) for Google Ad Manager Bulk Update Tool**

---

### **1. Overview**

This tool is designed to automate bulk updates to line items in Google Ad Manager using its API. The tool aims to simplify the process of updating multiple line items dynamically by importing a CSV, allowing updates to various fields such as targeting, budgets, impressions, and more. The application must be user-friendly, support error handling, and provide logging, rollback, and dynamic update capabilities.

### **2. Functional Requirements**

#### **2.1. User Stories**

1. **CSV Upload and Processing**:
    - As a user, I should be able to upload a CSV containing line items to update, and the system should dynamically process it to identify the changes.
2. **Line Item Updates**:

    - As a user, I should be able to dynamically update multiple fields of the line items, such as:
        - Line Item Type
        - Priority
        - Labels (Add/Remove)
        - Budget
        - Impression Goals
        - Pacing
        - Frequency Caps
        - Targeting (Inventory, Custom, Audience, Geography, Devices, etc.)

3. **Programmatic Line Item Updates**:
    - As a user, I should be able to manage programmatic lines, including:
        - Reopening and finalizing proposals
        - Requesting buyer acceptance
4. **Error Handling**:
    - The system must flag any line items that could not be updated and allow the user to view the reasons.
5. **Rollback**:

    - As a user, I should be able to roll back any changes made, reverting to the last known working state.

6. **Logging**:

    - All changes, including errors, updates, and rollbacks, must be logged with sufficient detail to understand the actions and any errors encountered.

7. **Dynamic Targeting Updates**:
    - Targeting rules, such as inventory ad units, placements, custom criteria, audience segments, etc., should be updated dynamically based on CSV input, preserving existing targeting when not explicitly updated.

---

### **3. Dashboard Page Layout**

#### **3.1. Main Layout**

The Dashboard will be the landing page of the app. It will contain the following sections:

1. **Navigation Bar** (top):

    - Logo
    - Links to: Home, Upload CSV, View Updates, Settings, Logs

2. **Bulk Upload Section** (Main Content Area):

    - **Upload CSV Button**: To upload the CSV file.
    - **Drag-and-Drop Area**: Users can drag and drop the CSV to upload.
    - **File Preview**: After upload, show the preview of the CSV content, allowing the user to map fields correctly (e.g., Line Item ID, Budget, Priority, etc.).

3. **Update Line Items Section**:

    - A table displaying current line items that need updates, with the following columns:
        - Line Item ID
        - Line Item Name
        - Current Values (Budget, Priority, etc.)
        - Action (Update Button)

4. **Error Log Section**:

    - Display errors encountered during the upload or update processes. Allow users to filter by type of error (validation, API error, etc.).

5. **Action Buttons**:
    - **Submit Changes**: Initiates the bulk update process.
    - **Rollback**: If any errors are flagged, this button will revert any updates to the last known working state.

#### **3.2. User Flow**

- **User uploads a CSV file**.
- **CSV preview is shown** with the option to confirm and map fields.
- **User selects the fields to update** (budget, targeting, etc.).
- **Updates are processed and applied** in real-time.
- If errors occur, the system flags them.
- **User can request rollback** if necessary.

---

### **4. Functionality Doc**

#### **4.1. Import CSV**

- **File Upload**: The system allows users to upload CSV files containing line item data.
- **CSV Parsing**: Each CSV file must be parsed to extract line item details and other parameters.
- **Field Mapping**: Users can map CSV columns to system fields, ensuring data matches.
- **Validation**: The system checks if each row in the CSV has valid data (line item IDs, budget values, targeting criteria).

#### **4.2. Update Line Items**

- **API Interaction**: Use the Google Ad Manager API to update line items.
- **Update Parameters**: Allow updates to the following fields:
    - **Line Item Type**
    - **Priority**
    - **Budget**
    - **Impression Goals**
    - **Targeting Criteria (Inventory, Custom, Audience, etc.)**
    - **Pacing**
    - **Frequency Caps**

#### **4.3. Error Flagging**

- If a line item fails to update, flag it with an error message indicating why (e.g., invalid line item ID, targeting criteria mismatch).

#### **4.4. Rollback**

- Keep track of previous values so that changes can be rolled back if needed.
- Implement a system where the user can revert any applied changes and return to the last known good state.

#### **4.5. Logging**

- Log every action (CSV uploaded, line items updated, errors encountered) in a detailed manner.
- Include timestamps, user details, changes made, and error messages.

---

### **5. Application Flow**

1. **Start**: The user visits the dashboard page.
2. **CSV Upload**: The user uploads a CSV file with line item data.
3. **CSV Validation**: System validates the CSV for correctness.
4. **Preview Data**: After validation, show a preview of the CSV data.
5. **Field Mapping**: User maps CSV columns to system fields.
6. **Confirm Changes**: The system confirms the changes and displays a summary.
7. **API Call**: The system calls the Google Ad Manager API to apply the updates.
8. **Error Handling**: If any errors are encountered, the system flags them.
9. **Rollback**: If necessary, the user can revert the changes.
10. **Log**: All actions and errors are logged.

---

### **6. Backend Design**

#### **6.1. Database Schema**

1. **Line Items Table**:

    - `id` (integer, primary key)
    - `line_item_id` (string)
    - `line_item_name` (string)
    - `budget` (float)
    - `priority` (integer)
    - `impression_goals` (json)
    - `targeting` (json) – Stores all targeting data (inventory, audience, etc.)
    - `labels` (json)
    - `created_at` (timestamp)
    - `updated_at` (timestamp)

2. **Log Table**:

    - `id` (integer, primary key)
    - `user_id` (integer)
    - `action` (string)
    - `description` (text)
    - `timestamp` (timestamp)
    - `line_item_id` (string) – Link to a line item that was updated

3. **Rollback Table**:
    - `id` (integer, primary key)
    - `line_item_id` (string)
    - `previous_data` (json) – Stores the previous state of line item data
    - `rollback_timestamp` (timestamp)

#### **6.2. Tech Stack**

- **Backend**: Laravel (PHP)
    - Use Laravel for API calls to Google Ad Manager, CSV parsing, and database interactions.
    - Use Laravel’s built-in logging functionality.
    - Database: SQLite for development; scalable for production.
- **Frontend**: React (No TypeScript)
    - Use DaisyUI for the UI components.
    - Display data, allow CSV uploads, show error logs, and interact with the backend via APIs.
- **APIs**: Google Ad Manager API for managing line items.

---

### **7. Edge Cases and Potential Errors**

#### **7.1. Edge Case Handling**

- **Missing Fields**: Ensure that if certain fields in the CSV are missing (e.g., Line Item ID), the system throws a validation error.
- **No Targeting Data**: If no targeting data is provided, the system should not remove existing targeting. It should preserve existing configurations.
- **API Failures**: Implement retry mechanisms in case of transient API errors.
- **Invalid Targeting Criteria**: If a line item contains invalid targeting, flag it with an error message and do not apply changes.

---

### **8. Sample Code**

#### **Backend (PHP / Laravel) - CSV Upload & API Call Example**

```php
public function uploadCSV(Request $request)
{
    $file = $request->file('csv');
    $csvData = array_map('str_getcsv', file($file));

    foreach ($csvData as $row) {
        $lineItemId = $row[0];
        $budget = $row[1];

        // Validate line item ID and other data
        if (!$this->validateLineItem($lineItemId)) {
            Log::error("Invalid line item ID: " . $lineItemId);
            continue;
        }

        // Prepare data for API call
        $lineItemData = [
            'line_item_id' => $lineItemId,
            'budget' => $budget,
            // ... other fields
        ];

        // API call to Google Ad Manager
        try {
            $this->updateLineItem($lineItemData);
        } catch (\Exception $e) {
            Log::error("Error updating line item: " . $e->getMessage());
        }
    }

    return response()->json(['status' => 'success']);
}

private function updateLineItem($data)
{
    // API logic to update line item via Google Ad Manager API
    // Call the API and update line item
}
```

---

### **.Docs**

- [Google Ad Manager API Documentation](https://developers.google.com/ad-manager/api/reference/v202411/LineItemService.LineItem)
- [Laravel Documentation](https://laravel.com/docs)
- [DaisyUI Documentation](https://daisyui.com/docs/install/)
