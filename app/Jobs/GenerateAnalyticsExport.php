<?php

namespace App\Jobs;

use App\Domain\FacebookAnalytics\Export\AnalyticsWorkbookExporter;
use App\Models\AnalyticsExport;
use App\Models\InteractionEdge;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GenerateAnalyticsExport implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;

    public function __construct(public string $exportId) {}

    public function handle(AnalyticsWorkbookExporter $xlsx): void
    {
        $export = AnalyticsExport::findOrFail($this->exportId);
        try {
            if ($export->format === 'csv') {
                $this->csv($export);
            } else {
                $path = 'private/exports/'.$export->id.'.xlsx';
                $xlsx->create($export->user_id, Storage::path($path));
                $export->update(['status' => 'completed', 'storage_path' => $path, 'completed_at' => now(), 'expires_at' => now()->addDays(7)]);
            }
        } catch (\Throwable$e) {
            $export->update(['status' => 'failed', 'error_message' => 'The export could not be generated.']);
            throw $e;
        }
    }

    private function csv(AnalyticsExport $export): void
    {
        $path = 'private/exports/'.$export->id.'.csv';
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, ['Person', 'Interaction Score', 'Messages Sent', 'Messages Received', 'Active Days', 'Sessions', 'Mutuality', 'Last Interaction', 'Trend']);
        foreach (InteractionEdge::with('person')->where('user_id', $export->user_id)->cursor() as $e) {
            fputcsv($stream, [$e->person->display_name, $e->overall_score, $e->messages_sent, $e->messages_received, $e->active_days, $e->session_count, $e->mutuality_score, $e->last_interaction_at?->toIso8601String(), $e->trend]);
        }rewind($stream);
        Storage::put($path, stream_get_contents($stream));
        fclose($stream);
        $export->update(['status' => 'completed', 'storage_path' => $path, 'completed_at' => now(), 'expires_at' => now()->addDays(7)]);
    }
}
