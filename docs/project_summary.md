# Project Summary: Google Ad Manager Bulk Update Tool

## Project Overview

This project is a web application designed to streamline the process of making bulk updates to Line Items within Google Ad Manager (GAM). It leverages the Google Ad Manager API to apply changes specified in an uploaded CSV file, significantly reducing the manual effort required for large-scale updates. The tool provides a user-friendly interface for uploading data, previewing changes, managing updates, and handling errors.

## Key Features

*   **CSV-Based Updates:** Users can upload CSV files containing Line Item IDs and the specific fields they wish to update.
*   **Dynamic Field Updates:** Supports updating a wide range of Line Item attributes, including:
    *   Line Item Type & Priority
    *   Budget & Impression Goals
    *   Pacing & Frequency Caps
    *   Labels (Adding/Removing)
    *   Targeting Criteria (Inventory, Custom, Audience, Geography, Devices, etc.) - designed to preserve existing targeting unless explicitly overwritten.
*   **Programmatic Line Item Management:** Includes functionality to manage programmatic deals (e.g., reopening proposals, requesting buyer acceptance).
*   **Dashboard Interface:** A central dashboard provides:
    *   CSV upload (including drag-and-drop).
    *   File preview and field mapping capabilities.
    *   Table view of Line Items queued for updates.
    *   Error logging display.
    *   Controls for submitting changes and initiating rollbacks.
*   **Error Handling:** Identifies and flags Line Items that fail to update, providing reasons for the failure.
*   **Rollback Functionality:** Allows users to revert batches of updates to the last known good state.
*   **Detailed Logging:** Records all significant actions, including uploads, updates, errors, and rollbacks, for auditing and debugging.

## Technical Challenges

*   **GAM API Complexity:** Interacting with the comprehensive GAM API, handling its nuances, potential rate limits, and different object structures.
*   **Atomicity of Updates:** Ensuring that bulk updates are applied reliably, potentially requiring transactional logic or careful state management.
*   **Rollback Implementation:** Designing a robust rollback mechanism that accurately reverts changes made via the external GAM API.
*   **Dynamic Targeting:** Implementing logic to correctly merge or replace complex targeting criteria based on CSV input without unintended side effects.
*   **Data Validation & Mapping:** Flexibly mapping diverse CSV column headers to specific GAM fields and validating the input data thoroughly.
*   **Security:** Securely managing GAM API credentials and protecting against potential vulnerabilities.

## Tech Stack Deep Dive

*   **Backend Framework:** Laravel (PHP) - Handles core application logic, API interactions, database operations, routing, and job queuing.
*   **Frontend Framework:** React (JavaScript) - Builds the user interface and interacts with the Laravel backend via APIs.
*   **Styling:** DaisyUI & Tailwind CSS - Provides UI components and utility classes for styling the frontend.
*   **External API:** Google Ad Manager (GAM) API - Used for retrieving and updating Line Item data.
*   **Database:** Not explicitly defined in existing code structure beyond dev (SQLite mentioned in PRD), likely intended to be a relational database like MySQL or PostgreSQL for production.
*   **Package Managers:** Composer (PHP), NPM (JavaScript).
*   **Build Tools:** Vite - For frontend asset bundling and development server.
*   **Testing:** PHPUnit (Backend), Jest (Frontend).
*   **Linting/Formatting:** ESLint, Prettier.

## Outcome

The goal is to create a robust, user-friendly tool that significantly improves the efficiency and accuracy of managing Google Ad Manager Line Items in bulk, saving time and reducing the potential for manual errors for ad operations teams. 