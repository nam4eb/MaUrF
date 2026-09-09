# Facebook Analytics implementation plan

## 1. Current architecture

The repository was empty. It is now a Laravel 11 modular monolith targeting PHP 8.2, PostgreSQL in production, SQLite for tests, Redis queues/cache in production, and a Vue 3 + TypeScript SPA. Laravel session authentication protects both the SPA and JSON API.

## 2. Existing components reusable

Laravel's authentication guard, validation, filesystem abstraction, queued jobs, cache, Eloquent, policies, and PHPUnit foundation are reused. No legacy application code existed.

## 3. Components built

The module adds import sessions, archive validation/extraction, a resilient parser registry, normalized people/conversation/message/activity records, aggregate edges/daily statistics, deterministic scores and trends, REST resources, dashboard views, CSV-compatible spreadsheet export, deletion, and privacy settings.

## 4. Database changes

Normalized tables are created for imports, people, friendships, conversations, participants, messages, Facebook interactions, daily stats, interaction edges, exports, privacy settings, and audit events. UUID ownership keys, fingerprints, composite uniqueness constraints, cascades, and analytics indexes are included.

## 5. API changes

All endpoints live under `/api`, use the `{data,meta,error}` envelope, obtain ownership from the authenticated session, validate filters server-side, and never accept a client `user_id`.

## 6. Frontend changes

The Vue SPA provides overview, imports, people, person detail, groups, network, exports and privacy settings. ECharts renders time series, heatmap and bounded graphs.

## 7. Background jobs

`ProcessFacebookImport` performs safe extraction, discovery, parsing in bounded batches and aggregation. `GenerateAnalyticsExport` produces a private export. Jobs are idempotent through archive and record fingerprints.

## 8. Security/privacy strategy

Privacy mode defaults on. Message text is discarded while structural metrics and a one-way content hash are retained. Extraction rejects traversal, symlinks, excessive files and excessive expanded size. Private files are authorized before download. Logs exclude message payloads. Source archives can be deleted after success.

## 9. Testing strategy

Unit tests cover scoring and session boundaries. Feature tests cover upload, ownership, analytics, deletion, unsafe archives and export. Synthetic Facebook JSON fixtures include Vietnamese names and emoji.

## 10. Migration strategy

This is a new database. Deploy schema before workers, then restart workers. PostgreSQL is recommended; migrations intentionally use portable Laravel column types so local SQLite tests remain representative.
