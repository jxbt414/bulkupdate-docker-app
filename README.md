# GAM Bulk Update Tool

A web application for bulk updating line items in Google Ad Manager (GAM). Built for media operations teams managing large-scale advertising campaigns.

## Features

- **Dynamic Updates (CSV)** - Upload a CSV mapping line items to changes, with intelligent field mapping
- **Static Updates (UI)** - Apply identical changes across multiple line items via a web form
- **Advanced Targeting** - Geographic, inventory, custom key-value, audience segments, CMS metadata, device category, day-part, and frequency caps
- **Line Item Properties** - Budget, priority, status, dates, cost type, impressions, labels
- **Preview & Validate** - Review all changes before applying them
- **Real-time Progress** - Track bulk update status with success/failure counts
- **Rollback** - Undo individual or batch updates
- **Activity Logs** - Full audit trail of all changes

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 11, PHP 8.3 |
| Frontend | React 18, Tailwind CSS, DaisyUI |
| Build | Vite 6 |
| Routing | Inertia.js |
| Database | MySQL 8.0 |
| Cache/Queue | Redis |
| GAM Integration | Google Ads PHP Library |
| Auth | Laravel Breeze + Sanctum |

## Getting Started

### Prerequisites

- Docker & Docker Compose

### Setup

1. Clone the repository:
   ```bash
   git clone <repo-url>
   cd bulkupdatetool
   ```

2. Copy the environment file:
   ```bash
   cp .env.example .env
   ```

3. Start the containers:
   ```bash
   docker compose up -d
   ```

4. Run migrations:
   ```bash
   docker compose exec app php artisan migrate
   ```

5. Access the app at **http://localhost:8080**

### Services

| Service | Port |
|---------|------|
| App (Nginx + PHP-FPM) | 8080 |
| MySQL | 3306 |
| Redis | 6380 |
| Vite Dev Server | 5173 |

### Google Ad Manager Configuration

Set these environment variables (or in `.env`):

| Variable | Description |
|----------|-------------|
| `GAM_JSON_KEY_PATH` | Path to the GAM service account JSON key file |
| `GAM_INI_PATH` | Path to `adsapi_php.ini` configuration file |

The app will start without GAM credentials - the UI will load but API operations (fetching/updating line items) will be unavailable until credentials are configured.

### Test GAM Connection

```bash
docker compose exec app php artisan gam:test
```

## Project Structure

```
app/
  Http/Controllers/     Route handlers
  Services/             Business logic (GAM, CSV, targeting)
  Models/               Eloquent models (User, LineItem, Log, Rollback)
  Providers/            Service providers (GAM, targeting, segments)
resources/js/
  Pages/                React page components
    LineItems/          Upload, Preview, StaticUpdate, Logs
    Auth/               Login, Register
  Components/           Reusable UI components
docker/                 Nginx, Supervisor, PHP, entrypoint configs
```

## Docker Services

- **app** - PHP 8.3-FPM + Nginx (serves the Laravel app)
- **mysql** - MySQL 8.0 database
- **redis** - Redis for caching, sessions, and queues
- **queue** - Laravel queue worker for background jobs
- **node** - Vite dev server for frontend hot-reload
