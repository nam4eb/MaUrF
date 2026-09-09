<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\PrivacyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('system/health', HealthController::class);
    Route::apiResource('imports', ImportController::class)->only(['index', 'store', 'show', 'destroy']);
    Route::post('imports/{id}/cancel', [ImportController::class, 'cancel']);
    Route::post('imports/{id}/retry', [ImportController::class, 'retry']);
    Route::get('imports/{id}/owner-candidates', [ImportController::class, 'ownerCandidates']);
    Route::post('imports/{id}/resolve-owner', [ImportController::class, 'resolveOwner']);
    Route::prefix('analytics')->group(function () {
        Route::get('overview', [AnalyticsController::class, 'overview']);
        Route::get('people', [AnalyticsController::class, 'people']);
        Route::get('people/{id}', [AnalyticsController::class, 'person']);
        Route::get('people/{id}/timeline', [AnalyticsController::class, 'timeline']);
        Route::get('people/{id}/heatmap', [AnalyticsController::class, 'heatmap']);
        Route::get('network', [AnalyticsController::class, 'network']);
        Route::get('groups', [AnalyticsController::class, 'groups']);
        Route::get('groups/{id}', [AnalyticsController::class, 'group']);
        Route::get('groups/{id}/timeline', [AnalyticsController::class, 'groupTimeline']);
        Route::get('groups/{id}/heatmap', [AnalyticsController::class, 'groupHeatmap']);
        Route::delete('data', [AnalyticsController::class, 'deleteAll']);
    });
    Route::post('exports', [ExportController::class, 'store']);
    Route::get('exports', [ExportController::class, 'index']);
    Route::get('exports/{id}', [ExportController::class, 'show']);
    Route::get('exports/{id}/download', [ExportController::class, 'download']);
    Route::get('settings/privacy', [PrivacyController::class, 'show']);
    Route::put('settings/privacy', [PrivacyController::class, 'update']);
});
