# API Flow Explanations

## Explanation: `POST /line-items/update` Flow

1.  **Request Initiation (Frontend):** The user, after previewing changes (from a CSV upload or static form) and confirming, triggers an action in the React frontend. This action sends a `POST` request to the `/line-items/update` endpoint. The request payload contains the batch of line item IDs and the corresponding data to be updated for each.
2.  **Routing (Laravel):** Laravel's router directs the incoming request to the `update` method within the `LineItemController`.
3.  **Controller Logic (`LineItemController`):**
    *   The controller receives the request data.
    *   It likely performs initial validation on the incoming batch data structure.
    *   It generates a unique `batch_id` to group this set of updates.
    *   Crucially, to avoid blocking the user interface during potentially long-running API updates, it **dispatches a Job** (e.g., `ProcessLineItemUpdatesJob`) onto Laravel's queue system. This job receives the update data and the `batch_id`.
    *   The controller immediately returns a response to the frontend, indicating that the update process has started (e.g., "Updates queued successfully").
4.  **Background Job Processing (Laravel Queue):**
    *   A queue worker picks up the `ProcessLineItemUpdatesJob`.
    *   The job iterates through each line item update in the batch.
    *   For each line item:
        *   It likely interacts with the `GoogleAdManagerService` to fetch the *current* state of the line item from the Google Ad Manager (GAM) API.
        *   It saves this current state (or relevant parts of it) to the `rollbacks` table in the database, associated with the `line_item_id` and `batch_id`. This is crucial for the rollback feature.
        *   It formats the update data according to the GAM API requirements.
        *   It calls the appropriate method in `GoogleAdManagerService` (e.g., `updateLineItem`) to send the update request to the GAM API.
        *   It handles the API response:
            *   **Success:** Logs the successful update in the `logs` table (associated with user, action, line item ID, batch ID).
            *   **Failure (API Error, Rate Limit, etc.):** Logs the error in the `logs` table. It might implement retry logic (exponential backoff as mentioned in docs) within the service or job itself. If retries fail, the error is logged permanently.
5.  **Completion/Notification:** Once the job has processed all line items in the batch, it might trigger a notification (e.g., via websockets, email, or an in-app notification system) to inform the user about the completion status (successes, failures).

## Explanation: Google Ad Manager API Authentication Flow

This flow uses a Service Account, which is suitable for server-to-server interactions where the application acts on its own behalf, not on behalf of an end-user.

1.  **Configuration:** The application reads the path to the Service Account's JSON credentials file from the `.env` variable `GOOGLE_APPLICATION_CREDENTIALS`. It also reads the GAM Network Code (`GOOGLE_AD_MANAGER_NETWORK_CODE`) and Application Name (`GOOGLE_AD_MANAGER_APPLICATION_NAME`) from the environment.
2.  **Initialization (`GoogleAdManagerService`):** When an instance of `GoogleAdManagerService` is created or when an API call is needed:
    *   It uses the Google API PHP Client Library.
    *   It loads the service account credentials from the specified JSON file.
    *   It specifies the necessary OAuth2 scopes required for accessing the Google Ad Manager API (e.g., `https://www.googleapis.com/auth/dfp`).
3.  **Token Request:** The client library, using the service account credentials, makes a request to the Google OAuth 2.0 authorization server to obtain an access token. This process involves signing a JWT (JSON Web Token) with the service account's private key.
4.  **Token Reception:** Google's authorization server validates the request and issues a short-lived access token.
5.  **Session Creation:** The obtained access token is used to build an `AdManagerSession` object (part of the `googleads/googleads-php-lib`). This session object encapsulates the authentication credentials and configuration (like network code, application name).
6.  **Service Instantiation:** The `AdManagerSession` is used by an `AdManagerServices` helper (or similar mechanism) to create instances of the specific GAM API services needed (e.g., `LineItemService`, `CustomTargetingService`).
7.  **API Calls:** These service instances (e.g., `$lineItemService`) are then used to make the actual API calls (like `getLineItemsByStatement`, `updateLineItems`). The client library automatically includes the valid access token in the headers of these requests.
8.  **Token Refresh:** The access tokens are short-lived. The Google API PHP Client Library typically handles the refreshing of these tokens automatically using the service account credentials when a token expires. The `GoogleAdManagerService` itself doesn't usually need to manage the token lifecycle explicitly; it relies on the underlying library.

## Explanation: Rollback Process

The rollback feature relies on the data saved *before* an update was applied.

1.  **Identify Batch:** The user interacts with the frontend (likely the `Logs` component) to find a specific update batch they want to roll back. They identify the `batch_id` associated with the operation.
2.  **Request Initiation (Frontend):** The user clicks a "Rollback" button for that batch, triggering a request (likely `POST /line-items/rollback`) to the backend, sending the `batch_id`.
3.  **Routing (Laravel):** The router directs the request to a `rollback` method in the `LineItemController` (or a dedicated `RollbackController`).
4.  **Controller Logic:**
    *   The controller receives the `batch_id`.
    *   Similar to the update process, it's best practice to **dispatch a Background Job** (e.g., `ProcessRollbackJob`) to handle the potentially numerous API calls required for the rollback. It passes the `batch_id` to the job.
    *   The controller returns an immediate response indicating the rollback has been initiated.
5.  **Background Job Processing (`ProcessRollbackJob`):**
    *   A queue worker picks up the `ProcessRollbackJob`.
    *   The job queries the `rollbacks` table in the database, retrieving all records matching the provided `batch_id`. These records contain the `line_item_id` and the `previous_data` (JSON) for each item updated in that batch.
    *   It iterates through each rollback record found.
    *   For each record:
        *   It retrieves the `line_item_id` and the `previous_data`.
        *   It interacts with the `GoogleAdManagerService`.
        *   It calls the `updateLineItem` method (or a similar update method) within the `GoogleAdManagerService`, passing the `line_item_id` and the `previous_data` (re-formatted as needed for the API update call). This effectively reverts the line item to its state *before* the original update in that batch occurred.
        *   It handles the API response:
            *   **Success:** Logs the successful rollback action in the `logs` table (potentially with a specific 'rollback' action type).
            *   **Failure:** Logs the error encountered during the rollback attempt. A rollback failure might require manual intervention.
6.  **Completion/Notification:** After attempting to roll back all items in the batch, the job notifies the user of the completion status, potentially highlighting any items that failed to roll back. 