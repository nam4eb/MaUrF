# Import performance benchmark

The committed generator `tests/Support/generate_large_fixture.php` creates data on demand and does not commit private or huge fixtures.

On 2026-09-03, PHP 8.2.12 on the local Windows environment parsed only the generated message array under a 128 MB memory limit:

| Records | Reader time | PHP peak memory |
|---:|---:|---:|
| 100,000 | 1.07 seconds | 2 MB |
| 1,000,000 | 11.01 seconds | 2 MB |

Commands used `php -d memory_limit=128M` and iterated `StreamingJsonReader::items(..., 'messages')`. Results measure JSON tokenization/decoding, not database insertion, archive extraction or queue latency. Production throughput depends heavily on database and storage performance; 500-row insert batches are used.
