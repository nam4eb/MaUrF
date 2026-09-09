<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateAnalyticsExport;
use App\Models\AnalyticsExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExportController extends Controller
{
    public function index(Request $r): JsonResponse
    {
        return response()->json(['data' => AnalyticsExport::where('user_id', $r->user()->id)->latest()->get(), 'meta' => [], 'error' => null]);
    }

    public function store(Request $r): JsonResponse
    {
        $data = $r->validate(['format' => 'sometimes|in:xlsx,csv', 'range' => 'sometimes|string', 'from' => 'sometimes|date', 'to' => 'sometimes|date|after_or_equal:from']);
        $format = $data['format'] ?? 'xlsx';
        $e = AnalyticsExport::create(['id' => (string) Str::uuid(), 'user_id' => $r->user()->id, 'status' => 'queued', 'format' => $format, 'filters' => $r->only(['range', 'from', 'to'])]);
        GenerateAnalyticsExport::dispatch($e->id);

        return response()->json(['data' => $e, 'meta' => [], 'error' => null], 202);
    }

    public function show(Request $r, string $id): JsonResponse
    {
        return response()->json(['data' => AnalyticsExport::where('user_id', $r->user()->id)->findOrFail($id), 'meta' => [], 'error' => null]);
    }

    public function download(Request $r, string $id)
    {
        $e = AnalyticsExport::where('user_id', $r->user()->id)->where('status', 'completed')->findOrFail($id);
        abort_unless(! $e->expires_at || $e->expires_at->isFuture(), 410);
        abort_unless($e->storage_path && Storage::exists($e->storage_path), 404);
        $type = $e->format === 'xlsx' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv; charset=UTF-8';

        return Storage::download($e->storage_path, 'facebook-analytics-'.$e->id.'.'.$e->format, ['Content-Type' => $type]);
    }
}
