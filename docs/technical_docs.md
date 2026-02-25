# Google Ad Manager Bulk Update Tool - Technical Documentation

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [System Requirements](#system-requirements)
3. [Installation and Setup](#installation-and-setup)
4. [Core Components](#core-components)
5. [API Integration](#api-integration)
6. [Database Schema](#database-schema)
7. [Authentication and Security](#authentication-and-security)
8. [Error Handling and Logging](#error-handling-and-logging)
9. [Testing](#testing)
10. [Deployment](#deployment)
11. [Troubleshooting](#troubleshooting)
12. [API Reference](#api-reference)

## Architecture Overview

The Google Ad Manager Bulk Update Tool is a Laravel-based web application with a React frontend that allows users to perform bulk updates to line items in Google Ad Manager. The application follows a Model-View-Controller (MVC) architecture with additional service layers for business logic.

### Technology Stack

- **Backend**: Laravel PHP framework (v10+)
- **Frontend**: React with Inertia.js
- **UI Framework**: Tailwind CSS with DaisyUI components
- **API Integration**: Google Ad Manager API v202411
- **Authentication**: Laravel's built-in authentication with Google OAuth
- **Database**: MySQL/PostgreSQL
- **Caching**: Laravel's built-in caching system
- **Job Queue**: Laravel's queue system for background processing

### Application Flow

1. User uploads a CSV file containing line item IDs and update values
2. System validates and parses the CSV file
3. User maps CSV columns to system fields
4. System previews changes before applying them
5. System applies changes via Google Ad Manager API
6. System logs all actions and provides rollback capability

## System Requirements

- PHP 8.1+
- Composer
- Node.js 16+
- npm or yarn
- MySQL 8.0+ or PostgreSQL 13+
- Google Ad Manager API credentials
- SSL certificate for production deployment

## Installation and Setup

### 1. Clone the Repository

```bash
git clone [repository-url]
cd bulkupdatetool
```

### 2. Install PHP Dependencies

```bash
composer install
```

### 3. Install JavaScript Dependencies

```bash
npm install
```

### 4. Environment Configuration

Copy the `.env.example` file to `.env` and update the following variables:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bulkupdatetool
DB_USERNAME=root
DB_PASSWORD=

GOOGLE_AD_MANAGER_NETWORK_CODE=your_network_code
GOOGLE_AD_MANAGER_APPLICATION_NAME=Bulk Update Tool
GOOGLE_APPLICATION_CREDENTIALS=path/to/credentials.json
```

### 5. Generate Application Key

```bash
php artisan key:generate
```

### 6. Run Migrations

```bash
php artisan migrate
```

### 7. Compile Assets

```bash
npm run dev
```

### 8. Start the Development Server

```bash
php artisan serve
```

### 9. Google Ad Manager API Configuration

1. Create a service account in the Google Cloud Console
2. Download the JSON credentials file
3. Place the credentials file in a secure location
4. Update the `GOOGLE_APPLICATION_CREDENTIALS` environment variable
5. Configure the `adsapi_php.ini` file with your network code and other settings

## Core Components

### Services

#### GoogleAdManagerService

The `GoogleAdManagerService` is the core service that interacts with the Google Ad Manager API. It handles:

- Authentication and session management
- Line item retrieval and updates
- Targeting criteria management
- Label management
- Error handling and retries

```php
// Example usage
$service = new GoogleAdManagerService($session);
$lineItem = $service->getLineItem($lineItemId);
$service->updateLineItem($lineItemId, $updateData);
```

#### CsvService

The `CsvService` handles CSV file processing, including:

- File validation
- Header validation
- Data parsing
- Type conversion

```php
// Example usage
$csvService = new CsvService();
$data = $csvService->validateAndParseCsv($uploadedFile);
```

#### CustomTargetingService

The `CustomTargetingService` manages custom targeting keys and values:

- Searching for custom targeting keys
- Retrieving values for a specific key
- Creating targeting expressions

#### AudienceSegmentService

The `AudienceSegmentService` handles audience segment operations:

- Searching for audience segments
- Retrieving segment details
- Creating audience targeting expressions

#### CmsMetadataService

The `CmsMetadataService` manages CMS metadata operations:

- Searching for CMS metadata keys
- Retrieving values for a specific key
- Creating CMS metadata targeting expressions

### Controllers

#### LineItemController

The `LineItemController` is the main controller that handles:

- CSV upload and processing
- Line item retrieval
- Line item updates
- Rollback operations
- Activity logging

```php
// Example endpoints
POST /line-items/upload - Upload CSV file
POST /line-items/update - Update line items
GET /line-items/logs - View activity logs
POST /line-items/rollback - Rollback changes
```

### Models

#### LineItem

The `LineItem` model represents a line item in Google Ad Manager and stores:

- Line item ID
- Line item name
- Line item type
- Budget
- Priority
- Impression goals
- Targeting criteria

#### Log

The `Log` model stores activity logs for all operations:

- User ID
- Action type
- Line item ID
- Batch ID
- Message
- Data (JSON)
- Timestamp

#### Rollback

The `Rollback` model stores previous state information for rollback operations:

- Line item ID
- Previous data (JSON)
- Batch ID
- Timestamp

### Frontend Components

#### StaticUpdate

The `StaticUpdate` component provides a UI for updating multiple line items with the same values:

- Line item ID input
- Field selection
- Value input
- Preview and confirmation

#### Upload

The `Upload` component handles CSV file uploads:

- File selection
- Drag-and-drop support
- File validation
- Progress indication

#### Preview

The `Preview` component shows a preview of changes before applying them:

- Line item details
- Current values
- New values
- Confirmation options

#### Logs

The `Logs` component displays activity logs:

- Filtering options
- Sorting options
- Detailed view
- Rollback options

## API Integration

### Google Ad Manager API

The application integrates with the Google Ad Manager API v202411 using the official PHP client library. The integration is managed through the `GoogleAdManagerService` class.

### Authentication Flow

1. Service account credentials are used to authenticate with the Google API
2. An OAuth2 token is obtained
3. The token is used to create an AdManager session
4. The session is used to create service instances (LineItemService, LabelService, etc.)

### API Requests

API requests are made using the service instances:

```php
// Example: Get a line item
$statement = new Statement("WHERE id = :id", [':id' => $lineItemId]);
$result = $this->lineItemService->getLineItemsByStatement($statement);
```

### Error Handling

API errors are caught and handled appropriately:

```php
try {
    $result = $this->lineItemService->getLineItemsByStatement($statement);
} catch (Exception $e) {
    Log::error("Failed to get line item: " . $e->getMessage());
    throw $e;
}
```

### Rate Limiting

The application implements exponential backoff for rate-limited requests:

```php
$retries = 0;
$maxRetries = 3;

while ($retries < $maxRetries) {
    try {
        return $this->lineItemService->getLineItemsByStatement($statement);
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'RATE_EXCEEDED') !== false) {
            $retries++;
            sleep(pow(2, $retries));
        } else {
            throw $e;
        }
    }
}
```

## Database Schema

### Line Items Table

```sql
CREATE TABLE line_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    line_item_id VARCHAR(255) NOT NULL,
    line_item_name VARCHAR(255) NULL,
    line_item_type VARCHAR(255) NULL,
    budget DECIMAL(15, 2) NULL,
    priority INT NULL,
    impression_goals INT NULL,
    targeting JSON NULL,
    labels JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id)
);
```

### Logs Table

```sql
CREATE TABLE logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    type VARCHAR(255) NOT NULL,
    action VARCHAR(255) NOT NULL,
    line_item_id VARCHAR(255) NULL,
    batch_id VARCHAR(255) NULL,
    message TEXT NULL,
    data JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id)
);
```

### Rollbacks Table

```sql
CREATE TABLE rollbacks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    line_item_id VARCHAR(255) NOT NULL,
    previous_data JSON NOT NULL,
    batch_id VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id)
);
```

## Authentication and Security

### User Authentication

The application uses Laravel's built-in authentication system with the following features:

- Email/password authentication
- Remember me functionality
- Password reset
- Email verification

### API Authentication

Google Ad Manager API authentication is handled using service account credentials:

- JSON credentials file stored securely
- Environment variables for sensitive information
- Token management and refresh

### Authorization

The application implements role-based access control:

- Admin role: Full access to all features
- User role: Limited access based on permissions
- Guest role: Access to public pages only

### CSRF Protection

Laravel's built-in CSRF protection is enabled for all forms and AJAX requests.

### Input Validation

All user inputs are validated using Laravel's validation system:

- CSV file validation
- Line item ID validation
- Field value validation
- API request validation

## Error Handling and Logging

### Exception Handling

The application uses try-catch blocks for exception handling:

```php
try {
    // Code that might throw an exception
} catch (Exception $e) {
    // Log the error
    Log::error("Error message: " . $e->getMessage());

    // Return an appropriate response
    return response()->json([
        'status' => 'error',
        'message' => 'An error occurred: ' . $e->getMessage()
    ], 500);
}
```

### Logging

The application uses Laravel's logging system with the following log levels:

- `emergency`: System is unusable
- `alert`: Action must be taken immediately
- `critical`: Critical conditions
- `error`: Error conditions
- `warning`: Warning conditions
- `notice`: Normal but significant condition
- `info`: Informational messages
- `debug`: Debug-level messages

### Activity Logging

All user actions are logged in the `logs` table:

- User ID
- Action type
- Line item ID
- Batch ID
- Message
- Data (JSON)
- Timestamp

## Testing

### Unit Tests

Unit tests are written using PHPUnit and focus on testing individual components:

- Service methods
- Controller methods
- Model methods

### Feature Tests

Feature tests focus on testing complete features:

- CSV upload and processing
- Line item updates
- Rollback functionality
- Activity logging

### API Tests

API tests focus on testing the integration with the Google Ad Manager API:

- Authentication
- Line item retrieval
- Line item updates
- Error handling

### Frontend Tests

Frontend tests are written using Jest and React Testing Library:

- Component rendering
- User interactions
- Form submissions
- Error handling

## Deployment

### Production Environment Setup

1. Set up a production server with PHP 8.1+, Composer, and Node.js
2. Configure a web server (Nginx or Apache)
3. Set up a database server (MySQL or PostgreSQL)
4. Configure SSL certificates
5. Set up environment variables

### Deployment Process

1. Clone the repository
2. Install dependencies
3. Compile assets for production
4. Run migrations
5. Configure the web server
6. Set up a process manager (PM2 or Supervisor)
7. Configure cron jobs for scheduled tasks

### Performance Optimization

- Enable OPCache for PHP
- Configure Redis for caching
- Use a CDN for static assets
- Implement database query optimization
- Enable HTTP/2 for the web server

## Troubleshooting

### Common Issues

#### Google Ad Manager API Connection Issues

- Check network connectivity
- Verify API credentials
- Check API quota and rate limits
- Verify network code and application name

#### CSV Processing Issues

- Check CSV file format (UTF-8 encoding)
- Verify required headers
- Check for special characters
- Verify data types

#### Line Item Update Issues

- Check line item IDs
- Verify field values
- Check for API errors
- Verify targeting criteria

### Debugging Tools

- Laravel Telescope for request/response monitoring
- Laravel Debugbar for performance monitoring
- Browser developer tools for frontend debugging
- Log files for error tracking

## API Reference

### Internal API Endpoints

#### Line Item Management

- `POST /line-items/upload` - Upload CSV file
- `POST /line-items/update` - Update line items
- `GET /line-items/{id}` - Get line item details
- `POST /line-items/rollback` - Rollback changes

#### Targeting Management

- `GET /line-items/labels` - Get available labels
- `GET /line-items/ad-units` - Get available ad units
- `GET /line-items/placements` - Get available placements
- `GET /line-items/custom-targeting/keys` - Get custom targeting keys
- `GET /line-items/custom-targeting/values` - Get custom targeting values
- `GET /line-items/audience-segments` - Get audience segments
- `GET /line-items/cms-metadata/keys` - Get CMS metadata keys
- `GET /line-items/cms-metadata/values` - Get CMS metadata values

#### Activity Logs

- `GET /line-items/logs` - Get activity logs
- `GET /line-items/logs/{id}` - Get log details

### Google Ad Manager API

The application uses the following Google Ad Manager API services:

- `LineItemService` - Manage line items
- `LabelService` - Manage labels
- `InventoryService` - Manage ad units
- `PlacementService` - Manage placements
- `CustomTargetingService` - Manage custom targeting
- `AudienceSegmentService` - Manage audience segments
- `CmsMetadataService` - Manage CMS metadata

For detailed API documentation, refer to the [Google Ad Manager API Reference](https://developers.google.com/ad-manager/api/reference/v202411/LineItemService.LineItem).
