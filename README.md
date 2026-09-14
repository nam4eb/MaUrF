# Interaction Atlas

Privacy-first analytics for Facebook/Messenger data that a user exports from their own Meta account. It is not a crawler and never logs into Facebook.

For a dependency-free local JSON folder sync and Vietnamese data dashboard using Node.js 24+, see [local Facebook data](docs/local-facebook-data.md). This companion SQLite workspace runs independently of the Laravel application.

## Setup

Requires PHP 8.2+, Composer and Node 20+. PostgreSQL and Redis are recommended; SQLite and the synchronous queue work locally.

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve
php artisan queue:work
```

Demo login: `demo@example.com` / `password`.

Production variables include `DB_CONNECTION=pgsql`, `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, `ANALYTICS_CONVERSATION_GAP_HOURS=8`, `ANALYTICS_RECENCY_DECAY_DAYS=90`, `ANALYTICS_MAX_EXTRACTED_BYTES`, `ANALYTICS_MAX_FILES`, and `DELETE_SOURCE_ARCHIVE_AFTER_IMPORT=true`.

Run tests with `php artisan test`. See [implementation plan](docs/facebook-analytics-implementation-plan.md), [export guide](docs/facebook-export-guide.md), and [privacy architecture](docs/privacy-architecture.md).
