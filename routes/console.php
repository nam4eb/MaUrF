<?php

use App\Models\AnalyticsExport;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::call(function (): void {
    AnalyticsExport::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<=', now())
        ->orderBy('id')
        ->chunkById(100, function ($exports): void {
            foreach ($exports as $export) {
                if ($export->path) {
                    Storage::disk('local')->delete($export->path);
                }
                $export->delete();
            }
        });
})->name('analytics:purge-expired-exports')->daily()->withoutOverlapping();
