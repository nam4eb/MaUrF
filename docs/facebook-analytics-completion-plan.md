# Facebook analytics completion plan

Audit date: 2026-09-03. The repository is a new Laravel 11.56 application targeting PHP 8.2, Vue 3/TypeScript, SQLite locally, PostgreSQL/Redis in production. There are no policies or API resources; ownership is currently enforced directly in controllers. Docker/Horizon are absent. Composer's lock contains the Laravel runtime, but `vendor/autoload.php` is absent because dependency retrieval has not completed.

| Feature | Expected | Existing | Verified | Missing | Action | Test required | Status |
|---|---|---|---|---|---|---|---|
| Composer/runtime | Installable Laravel 11 | Lockfile valid | `composer validate` passed; connectivity failed through `127.0.0.1:9` | Vendor tree | Retry approved network; keep exact blocker | Artisan bootstrap | Blocked by environment |
| Database | Clean SQLite/PostgreSQL migrations | One analytics migration | PHP syntax only | Runtime migration and PostgreSQL proof | Run fresh migration when vendor exists; correct schema defects | SQLite + PostgreSQL integration | Pending runtime |
| Messenger import | Tolerant, bounded, idempotent | Thread JSON parser, 500-row DB chunks | Synthetic fixture/source audit | Whole-file `json_decode`; fragile owner inference | Add parser contracts, streaming reader and owner resolver | Fixture + golden path | In progress |
| Friends import | Current/removed/requested | No friends table/parser | Confirmed absent | Entire feature | Add normalized table and schema-tolerant parser | Structural fixtures | Missing |
| Engagement | Posts/comments/reactions/mentions/tags | Flags only; no normalized interactions | Confirmed absent | Entire feature | Add interaction schema and modular parsers | Structural fixtures | Missing |
| Parser diagnostics | Coverage/confidence/counters | Import warnings only | Source audited | Per-file diagnostics/coverage | Add manifests and coverage service | Unknown schema test | Missing |
| Owner identity | Profile/explicit/manual | Low-confidence title inequality inference | Source audited | State, candidates, API/UI, reaggregation | Add mappings/resolver/endpoints | Auto/manual tests | Missing |
| Identity resolution | High-confidence cross-source merge | Name hash merges all exact names | Source audited | Safe evidence-based behavior | Stop name-only cross-dataset merges; use scoped fingerprints | Same-name test | Defective |
| Streaming JSON | Bounded per-record parsing | `file_get_contents` + `json_decode` | Confirmed | Streaming abstraction | Implement dependency-free incremental array reader | 100k benchmark | Missing |
| Aggregation | Idempotent, complete imports only | Rebuilds from all messages | Source audited | Failed/partial exclusion; Facebook metrics; 90-day trends | Filter completed imports and extend metrics | Retry/partial tests | Defective |
| Scores | Six explainable dimensions | Five Messenger dimensions; Facebook stored as zero | Score smoke passed | Availability metadata and engagement score | Add coverage-aware weighting/components | Comprehensive unit tests | Partial |
| Groups | Contribution/timeline/heatmap/detail | List only | Source audited | Detail analytics and UI | Add aggregate queries/APIs/detail view | Group fixture tests | Missing |
| Network | Bounded owner-star graph | Top 25/100 graph | Frontend build passed | Date/score filters | Add validated filter | API test | Partial |
| XLSX | Private multi-sheet workbook | UTF-8 CSV | Source audited | Real XLSX | Implement native OpenXML streaming writer; retain CSV | Open ZIP/XML + ownership/privacy tests | Missing |
| Deletion | Import-aware reaggregation/full isolation | Deletes import and rebuilds; full delete misses stored export files | Source audited | Artifact cleanup and tests | Central deletion action | Two-user deletion tests | Partial |
| Cancellation/retry | Cooperative cancellation | None | Confirmed | State/endpoints/checkpoints | Add cancellation checks and completed-file manifests | Cancellation/idempotency tests | Missing |
| Archive safety | Slip/bomb/file-count protection | Relative/absolute/NUL and declared-size guards | PHP lint | Symlink/encryption/extension handling tests | Harden attributes and add tests | Malicious ZIP tests | Partial |
| UI | Operational analytics SPA | Overview/people/groups/network/import/export/settings | Production Vite build passed | Details, owner resolution, coverage/error states | Extend existing SPA without rebuild | Manual/browser smoke | Partial |
| Test suite | Broad executed coverage | 6 application test methods plus skeleton tests | PHP lint only | Runtime execution and acceptance tests | Expand suite; run once Composer succeeds | PHPUnit | Environment-blocked |
| Production config | Complete documented env | Laravel defaults; analytics config exists | Audited | Consistent requested variable names, deployment guide | Update `.env.example` and docs | Config test | Missing |

## Execution order

1. Recover dependencies and establish migrations/tests as gate.
2. Correct schema, ownership, deletion and partial-import consistency.
3. Add parser contracts, streaming reader, owner and identity resolution.
4. Add friends and engagement adapters with coverage diagnostics.
5. Add true XLSX, group analytics, cancellation/checkpoints and security tests.
6. Run synthetic golden path, performance generator, frontend build and all available database/queue verification.

Real sanitized Meta exports are not present. Compatibility can therefore be fixture-verified only; documentation and UI must say so.
