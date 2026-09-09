<?php

namespace App\Jobs;

use App\Domain\FacebookAnalytics\Analytics\Aggregator;
use App\Domain\FacebookAnalytics\Import\OwnerIdentityResolver;
use App\Domain\FacebookAnalytics\Import\SafeArchiveExtractor;
use App\Domain\FacebookAnalytics\Parser\ParserRegistry;
use App\Models\ImportSession;
use App\Models\ParserDiagnostic;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessFacebookImport implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;

    public int $timeout = 3600;

    public int $tries = 3;

    public function __construct(public string $importId) {}

    public function handle(SafeArchiveExtractor $extractor, ParserRegistry $registry, OwnerIdentityResolver $owners, Aggregator $aggregator): void
    {
        $import = ImportSession::findOrFail($this->importId);
        if (in_array($import->status, ['completed', 'completed_with_warnings', 'cancelled'])) {
            return;
        }$import->update(['status' => 'extracting', 'started_at' => $import->started_at ?? now(), 'progress' => 5, 'error_message' => null]);
        $dir = storage_path('app/private/imports/'.$import->id.'/extracted');
        try {
            $source = Storage::path($import->storage_path);
            if (str_ends_with(strtolower($import->original_filename), '.json')) {
                $dir = dirname($source);
                $files = [basename($source)];
            } else {
                $files = $extractor->extract($source, $dir);
            }$import->update(['status' => 'scanning', 'total_files' => count($files), 'progress' => 15]);
            $warnings = [];
            $records = 0;
            $skipped = 0;
            $coverage = [];
            $profileName = null;
            foreach ($files as $i => $file) {
                $import->refresh();
                if ($import->status === 'cancelled') {
                    $this->cleanupPartial($import);

                    return;
                }$relative = str_replace('\\', '/', $file);
                $path = $dir.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $file);
                $parser = $registry->matching($path);
                if (! $parser) {
                    $warnings[] = $relative.': unsupported JSON structure';
                    $skipped++;

                    continue;
                }$diagnostic = ParserDiagnostic::firstOrCreate(['import_session_id' => $import->id, 'file_path' => $relative, 'parser_name' => $parser->name()], ['detected_schema' => $parser->schemaVersion($path), 'confidence' => $parser->confidence($path)]);
                if ($diagnostic->completed) {
                    $records += $diagnostic->records_imported;
                    $skipped += $diagnostic->records_skipped;
                    foreach ($diagnostic->datasets ?? [] as $dataset) {
                        $coverage[$dataset] = 'available';
                    }
                    $profileName = $diagnostic->context['profile_name'] ?? $profileName;

                    continue;
                }try {
                    $result = $parser->parse($path, $import);
                    $diagnostic->update(['detected_schema' => $result->schema, 'confidence' => $result->confidence, 'records_seen' => $result->seen, 'records_imported' => $result->imported, 'records_skipped' => $result->skipped, 'warnings' => $result->warnings, 'datasets' => $result->datasets, 'context' => $result->context, 'completed' => true]);
                    $records += $result->imported;
                    $skipped += $result->skipped;
                    $warnings = array_merge($warnings, $result->warnings);
                    foreach ($result->datasets as $d) {
                        $coverage[$d] = 'available';
                    }$profileName = $result->context['profile_name'] ?? $profileName;
                } catch (\Throwable $e) {
                    $diagnostic->update(['warnings' => ['Parser failed: '.$e->getMessage()]]);
                    $warnings[] = $relative.': parser failed';
                }$import->update(['status' => 'parsing', 'processed_files' => $i + 1, 'records_processed' => $records, 'records_skipped' => $skipped, 'progress' => 15 + (int) (60 * ($i + 1) / max(count($files), 1))]);
            }$owners->finalize($import, $profileName);
            $all = ['messenger', 'friends', 'posts', 'comments', 'reactions', 'mentions', 'tags'];
            foreach ($all as $d) {
                $coverage[$d] = $coverage[$d] ?? 'unavailable';
            }$import->update(['status' => 'aggregating', 'progress' => 80, 'data_coverage' => $coverage]);
            $aggregator->rebuild($import->user_id, $import->id);
            $status = $warnings ? 'completed_with_warnings' : 'completed';
            $import->update(['status' => $status, 'progress' => 100, 'warnings' => $warnings, 'warning_count' => count($warnings), 'completed_at' => now()]);
            if (config('facebook_analytics.delete_source_after_import') && ! DB::table('privacy_settings')->where('user_id', $import->user_id)->value('retain_archives')) {
                Storage::delete($import->storage_path);
            }if (str_ends_with(strtolower($import->original_filename), '.zip')) {
                File::deleteDirectory($dir);
            }
        } catch (\Throwable $e) {
            $import->update(['status' => 'failed', 'error_message' => 'The import could not be completed safely.', 'completed_at' => now()]);
            Log::error('Facebook import failed', ['import_id' => $import->id, 'user_id' => $import->user_id, 'exception' => $e::class]);
            throw $e;
        }
    }

    private function cleanupPartial(ImportSession $import): void
    {
        DB::table('messages')->where('source_import_id', $import->id)->delete();
        DB::table('facebook_interactions')->where('source_import_id', $import->id)->delete();
        DB::table('friendships')->where('source_import_id', $import->id)->delete();
        DB::table('conversations')->where('source_import_id', $import->id)->delete();
    }
}
