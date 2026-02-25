# Google Ad Manager Bulk Update Tool - User Guide

## Table of Contents

1. [Introduction](#introduction)
2. [Getting Started](#getting-started)
3. [Dashboard Overview](#dashboard-overview)
4. [Static Bulk Updates](#static-bulk-updates)
5. [Dynamic Bulk Updates (CSV)](#dynamic-bulk-updates-csv)
6. [Targeting Criteria](#targeting-criteria)
7. [Activity Logs](#activity-logs)
8. [Rollback Operations](#rollback-operations)
9. [Best Practices](#best-practices)
10. [Troubleshooting](#troubleshooting)
11. [FAQ](#faq)

## Introduction

The Google Ad Manager Bulk Update Tool is a powerful application designed to simplify the process of updating multiple line items in Google Ad Manager. This tool allows you to make bulk changes to various line item properties, including targeting criteria, budget, priority, impression goals, and more.

### Key Features

- **Static Bulk Updates**: Update multiple line items with the same values
- **Dynamic Bulk Updates**: Update multiple line items with different values using CSV files
- **Targeting Management**: Update targeting criteria including custom targeting, audience segments, and CMS metadata
- **Activity Logging**: Track all changes made to line items
- **Rollback Capability**: Revert changes if needed
- **User-Friendly Interface**: Intuitive UI with validation and error handling

## Getting Started

### Accessing the Tool

1. Open your web browser and navigate to the application URL
2. Log in using your credentials
3. You will be directed to the Dashboard

### Navigation

The main navigation menu includes:

- **Dashboard**: Overview of recent activity
- **Static Update**: Update multiple line items with the same values
- **CSV Upload**: Update multiple line items with different values using a CSV file
- **Logs**: View activity logs and perform rollback operations
- **Settings**: Configure application settings

## Dashboard Overview

The Dashboard provides an overview of recent activity and quick access to the main features of the application.

### Dashboard Components

- **Recent Activity**: Shows recent updates and their status
- **Quick Actions**: Buttons for common tasks (Static Update, CSV Upload, View Logs)
- **Statistics**: Summary of recent operations (successful updates, errors, etc.)

## Static Bulk Updates

Static bulk updates allow you to update multiple line items with the same values.

### Step 1: Enter Line Item IDs

1. Navigate to the **Static Update** page
2. Enter the line item IDs in the text area (one per line or comma-separated)
3. Click **Load Line Items** to retrieve the line items

### Step 2: Select Fields to Update

Select the fields you want to update:

- **Priority**: Set the priority level (1-16)
- **Budget**: Set the budget amount
- **Impression Goals**: Set the impression goals
- **Status**: Change the line item status
- **Start/End Date**: Set the start and end dates
- **Delivery Rate**: Set the delivery rate type
- **Cost Type**: Set the cost type and cost per unit
- **Labels**: Add or remove labels

### Step 3: Configure Targeting Criteria

Configure targeting criteria as needed:

- **Ad Units**: Select ad units to include or exclude
- **Placements**: Select placements to include
- **Custom Targeting**: Add custom targeting key-value pairs
- **Audience Segments**: Add audience segments
- **CMS Metadata**: Add CMS metadata key-value pairs
- **Geography**: Select locations to include or exclude
- **Device Categories**: Select device categories to target
- **Day Parts**: Configure day and time targeting

### Step 4: Preview and Submit

1. Click **Preview Changes** to see a summary of the changes
2. Review the changes carefully
3. Click **Submit Changes** to apply the updates
4. Wait for the confirmation message

## Dynamic Bulk Updates (CSV)

Dynamic bulk updates allow you to update multiple line items with different values using a CSV file.

### Step 1: Prepare the CSV File

Create a CSV file with the following structure:

- First row: Header row with field names
- Each subsequent row: Line item data

Required column:

- `line_item_id`: The ID of the line item to update

Optional columns (include only the fields you want to update):

- `line_item_name`: The name of the line item
- `budget`: The budget amount
- `priority`: The priority level (1-16)
- `impression_goals`: The impression goals
- `targeting`: Targeting criteria (JSON format)
- `labels`: Labels (comma-separated)

Example CSV:

```
line_item_id,budget,priority,impression_goals
12345678,1000,8,100000
87654321,2000,10,200000
```

### Step 2: Upload the CSV File

1. Navigate to the **CSV Upload** page
2. Click **Choose File** or drag and drop your CSV file
3. Wait for the file to be processed

### Step 3: Map CSV Columns

1. Map each CSV column to the corresponding field in the system
2. Click **Continue** to proceed

### Step 4: Preview and Submit

1. Review the changes that will be applied
2. Select the line items you want to update
3. Click **Update Selected** to apply the changes
4. Wait for the confirmation message

## Targeting Criteria

The Bulk Update Tool allows you to update various targeting criteria for line items.

### Custom Targeting

Custom targeting allows you to target line items based on key-value pairs.

To add custom targeting:

1. In the **Custom Targeting** section, click the dropdown to search for a key
2. Select a key from the dropdown
3. Select a logical operator (is any of, is none of)
4. Search for and select values for the key
5. Click **Add** to add the targeting criteria

### Audience Segments

Audience segments allow you to target line items based on user segments.

To add audience segments:

1. In the **Audience Segments** section, search for segments
2. Select a logical operator (is any of, is none of)
3. Select segments from the dropdown
4. Click **Add** to add the targeting criteria

### CMS Metadata

CMS metadata allows you to target line items based on content metadata.

To add CMS metadata:

1. In the **CMS Metadata** section, search for a key
2. Select a key from the dropdown
3. Select a logical operator (is any of, is none of)
4. Search for and select values for the key
5. Click **Add** to add the targeting criteria

### Geography

Geography targeting allows you to target line items based on geographic locations.

To add geography targeting:

1. In the **Geography** section, search for locations
2. Select locations to include or exclude
3. Click **Add** to add the targeting criteria

### Device Categories

Device category targeting allows you to target line items based on device types.

To add device category targeting:

1. In the **Device Categories** section, select device categories
2. Click **Add** to add the targeting criteria

### Day Parts

Day part targeting allows you to target line items based on days of the week and times of day.

To add day part targeting:

1. In the **Day Parts** section, select days of the week
2. Set start and end times for each day
3. Click **Add** to add the targeting criteria

## Activity Logs

The Logs page allows you to view a history of all operations performed in the application.

### Viewing Logs

1. Navigate to the **Logs** page
2. Use the filters to narrow down the logs:
    - **Type**: Filter by log type (info, error, warning)
    - **Action**: Filter by action type (update, rollback, etc.)
    - **Line Item ID**: Filter by line item ID
    - **Batch ID**: Filter by batch ID
    - **Date Range**: Filter by date range
3. Click on a log entry to view details

### Log Details

Each log entry includes:

- **Type**: The type of log (info, error, warning)
- **Action**: The action performed
- **Line Item ID**: The ID of the line item (if applicable)
- **Batch ID**: The ID of the batch operation (if applicable)
- **Message**: A description of the action
- **Data**: Additional data related to the action
- **Timestamp**: The date and time of the action
- **User**: The user who performed the action

## Rollback Operations

The Rollback feature allows you to revert changes made to line items.

### Performing a Rollback

1. Navigate to the **Logs** page
2. Find the log entry for the operation you want to roll back
3. Click on the log entry to view details
4. Click the **Rollback** button
5. Confirm the rollback operation
6. Wait for the confirmation message

### Rollback Limitations

- You can only roll back operations that modified line items
- You cannot roll back a rollback operation
- Some changes may not be reversible if the line item has been modified by other operations

## Best Practices

### CSV File Preparation

- Use UTF-8 encoding for CSV files
- Include only the columns you need to update
- Verify line item IDs before uploading
- Keep the file size manageable (under 1000 rows)

### Line Item Updates

- Update related line items together
- Preview changes before submitting
- Use descriptive batch names for easier tracking
- Check for errors after updates

### Targeting Criteria

- Be careful when removing targeting criteria
- Test targeting criteria on a small set of line items first
- Use logical operators appropriately
- Verify targeting criteria after updates

## Troubleshooting

### Common Issues

#### CSV Upload Errors

- **Invalid CSV format**: Ensure the CSV file is properly formatted and uses UTF-8 encoding
- **Missing required columns**: Ensure the CSV file includes the `line_item_id` column
- **Invalid data types**: Ensure numeric fields contain valid numbers

#### Line Item Update Errors

- **Line item not found**: Verify the line item ID exists in Google Ad Manager
- **Permission denied**: Ensure you have permission to update the line item
- **Invalid field values**: Ensure field values are valid for the line item type

#### Targeting Criteria Errors

- **Key not found**: Ensure the custom targeting key exists in Google Ad Manager
- **Value not found**: Ensure the custom targeting value exists for the selected key
- **Invalid targeting expression**: Ensure the targeting expression is valid

### Error Messages

- **"Failed to load line items"**: The system could not retrieve the line items. Verify the line item IDs and try again.
- **"Failed to update line items"**: The system could not update the line items. Check the error details for more information.
- **"Failed to load custom targeting keys"**: The system could not retrieve custom targeting keys. Try refreshing the page.
- **"Failed to load custom targeting values"**: The system could not retrieve custom targeting values for the selected key. Try selecting a different key.

## FAQ

### General Questions

**Q: How many line items can I update at once?**  
A: You can update up to 500 line items in a single operation. For larger updates, split them into multiple operations.

**Q: Can I update programmatic line items?**  
A: Yes, but some fields may not be updatable for programmatic line items, and changes may require buyer acceptance.

**Q: Can I schedule updates for a future time?**  
A: No, updates are applied immediately. However, you can set future start dates for line items.

### CSV Upload Questions

**Q: What format should my CSV file be in?**  
A: Your CSV file should be in UTF-8 format with headers in the first row. The only required column is `line_item_id`.

**Q: Can I download a CSV template?**  
A: Yes, you can download a template from the CSV Upload page.

**Q: What happens if my CSV file has errors?**  
A: The system will validate the CSV file and show any errors before processing. You can fix the errors and upload the file again.

### Targeting Questions

**Q: Can I add and remove targeting criteria in the same operation?**  
A: Yes, you can add new targeting criteria and remove existing ones in the same operation.

**Q: What happens to existing targeting criteria when I update a line item?**  
A: Existing targeting criteria are preserved unless you explicitly update them.

**Q: Can I target multiple audience segments?**  
A: Yes, you can target multiple audience segments using the "is any of" logical operator.

### Rollback Questions

**Q: How long are rollback data kept?**  
A: Rollback data are kept for 30 days.

**Q: Can I roll back specific fields only?**  
A: No, a rollback operation reverts all changes made in the original operation.

**Q: What happens if a rollback fails?**  
A: The system will log the failure and provide an error message. You may need to manually update the line items.
