# Sample Interview Transcript: GAM Bulk Update Tool Project

This transcript provides sample answers to common interview questions regarding the project, incorporating discussions about architecture, features, patterns, database design, challenges, learnings, and testing.

**(Interviewer): Can you tell me about a challenging or significant project you've worked on recently?**

**(You):** "Certainly. A significant recent project involved developing a **Google Ad Manager (GAM) Bulk Update Tool**. The core business problem was the inefficiency of manually updating potentially thousands of GAM line items. This tool streamlined that process, allowing users to perform bulk updates via CSV uploads or static criteria.

It was built using **Laravel** for the backend API and **React with Inertia.js** for the frontend. Key features included CSV validation and parsing, a mapping interface, data preview, asynchronous update processing via background jobs, detailed logging for auditing, and a crucial rollback mechanism based on update batches."

**(Interviewer): Can you elaborate on the architecture?**

**(You):** "The architecture followed standard **Laravel MVC** principles. Controllers handled HTTP requests, Eloquent Models managed database interactions (representing logs, rollbacks, etc.), and React/Inertia components served as the View layer.

A key part was a **Service Layer** (`app/Services`) containing classes like `GoogleAdManagerService` and `CsvService`. This encapsulated external API interactions and complex business logic, keeping the controllers lean. For handling the potentially long-running GAM updates without blocking users, we utilized Laravel's **Queue system**, dispatching **Jobs** to process updates asynchronously in the background."

**(Interviewer): What design patterns did you use?**

**(You):** "Beyond MVC, we heavily relied on **Dependency Injection** via Laravel's Service Container, injecting services like `GoogleAdManagerService` into controllers. The background processing used the **Command Pattern** via Laravel Jobs. We also utilized Laravel's **Facades** (`Log`, `Auth`, `Bus`) and implemented a **Service Layer**. We also used **Traits** for reusable logic, like `LineItemLocking`.

*(Self-aware addition)*: While implementing these, I learned the importance of strictly adhering to the **Single Responsibility Principle**. In hindsight, I'd refactor the initial larger controller and service classes into more granular components to further improve modularity and testability."

**(Interviewer): Can you describe the database design?**

**(You):** "The database design primarily supported auditing and rollbacks. We had three main tables defined using Laravel **Migrations**:
1.  `logs`: An activity log tracking user actions, line items affected, status (success/error), and linking operations via a `batch_id`.
2.  `rollbacks`: Stored a JSON snapshot of line item data (`previous_data`) *before* an update, linked via the `batch_id`. This was essential for reverting changes.
3.  `line_items`: Intended as a local reference/cache for GAM line item data (using JSON for complex fields like targeting).
Relationships were mainly logical via `batch_id` or explicit foreign keys like `logs.user_id`. We ensured relevant columns like `batch_id` and `line_item_id` were indexed for query performance."

**(Interviewer): How did you handle the integration with the Google Ad Manager API?**

**(You):** "All interactions with the GAM API were encapsulated within the `GoogleAdManagerService`. We used the official Google Ads API PHP client library, handling authentication via a Service Account suitable for server-to-server communication. The service provided methods for fetching line items, updating them, retrieving related data like targeting keys or labels, etc.

*(Self-aware addition based on SOAP/REST)*: This specific integration involved [mention SOAP if applicable, or focus on the complexity regardless]. While this used [SOAP/complex structure], I also have experience consuming RESTful APIs using standard practices like HTTP verbs, JSON payloads, and handling authentication via tokens or keys. A key learning for performance, regardless of API type, was the importance of using **batch operations** (like GAM's `updateLineItems`) instead of sequential single calls within loops for bulk updates."

**(Interviewer): What were some major challenges and how did you address them?**

**(You):** "A major challenge was handling the **asynchronous nature and potential slowness** of bulk updates via the external GAM API. We addressed this by implementing **Laravel Jobs and Queues**, moving the processing logic out of the web request cycle.
Another challenge was ensuring **data integrity and providing rollbacks**. This was solved by designing the `rollbacks` table to store the state before each update and linking operations via a `batch_id`.
Performance with the external API was also a consideration, leading to the realization that implementing **batch API calls** was a necessary optimization over initial sequential processing."

**(Interviewer): How did you approach testing?**

**(You):** "*(Acknowledge current state honestly but pivot)*: While the initial focus was on core functionality delivery, a robust testing strategy is essential. My approach would include:
*   **Unit Tests (PHPUnit):** For services like `CsvService` and especially `GoogleAdManagerService`, heavily **mocking** the external GAM API client to test our logic in isolation.
*   **Feature Tests (Laravel HTTP Tests):** Covering key user flows like CSV upload -> preview -> update confirmation, ensuring the correct job is dispatched. These would mock the Queue and potentially the Service layer.
*   **Integration Tests:** Testing the interaction between components, for example, ensuring the Update Job correctly interacts with the mocked GAM service and writes the expected records to a test database (`Rollback` and `Log` models).
This layered approach ensures reliability and allows for confident refactoring." 