# Production deployment

Use PHP 8.2+, PostgreSQL 15+, Redis, a private S3-compatible disk or encrypted private volume, and a supervised queue worker. Configure TLS, private buckets, encrypted backups and short artifact retention; application-level encryption at rest is not claimed.

```bash
composer install --prefer-dist --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build
php artisan config:cache
php artisan route:cache
php artisan migrate --force
php artisan queue:restart
```

Run `php artisan queue:work redis --queue=default --tries=3 --timeout=3600` under a process supervisor. Horizon is not installed. Restrict `/up` at the load balancer if infrastructure details are sensitive.

Required settings are documented in `.env.example`. Set PostgreSQL, Redis, a private filesystem disk, upload limits at both reverse proxy and PHP levels, and `DELETE_SOURCE_ARCHIVE_AFTER_IMPORT=true`. Run Laravel's scheduler every minute; the application removes expired export files and their database rows daily. Failed-import archives follow the organization's retention policy.

## Database verification

Before release, run both SQLite CI and a PostgreSQL staging suite. Verify JSON casting, UTC timestamps, UUID constraints, cascades, cursor iteration and date grouping. The current suite was verified on PostgreSQL 17 in a disposable container; repeat it against the actual staging infrastructure before release.
