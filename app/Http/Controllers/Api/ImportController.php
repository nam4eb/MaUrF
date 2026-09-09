<?php

namespace App\Http\Controllers\Api;

use App\Domain\FacebookAnalytics\Analytics\Aggregator;
use App\Domain\FacebookAnalytics\Import\OwnerIdentityResolver;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessFacebookImport;
use App\Models\ImportSession;
use App\Models\SocialPerson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportController extends Controller
{
    public function index(Request $r): JsonResponse
    {
        return response()->json(['data' => ImportSession::where('user_id', $r->user()->id)->latest()->paginate(20), 'meta' => [], 'error' => null]);
    }

    public function store(Request $r): JsonResponse
    {
        $r->validate(['archive' => ['required', 'file', 'mimes:zip,json', 'max:'.config('facebook_analytics.max_upload_kb')], 'privacy_mode' => ['sometimes', 'boolean']]);
        $file = $r->file('archive');
        $hash = hash_file('sha256', $file->getRealPath());
        if ($old = ImportSession::where('user_id', $r->user()->id)->where('fingerprint', $hash)->first()) {
            return response()->json(['data' => $old, 'meta' => ['duplicate' => true], 'error' => null], 200);
        }$id = (string) Str::uuid();
        $path = $file->storeAs('private/imports/'.$id, $file->getClientOriginalName());
        $import = ImportSession::create(['id' => $id, 'user_id' => $r->user()->id, 'original_filename' => $file->getClientOriginalName(), 'storage_path' => $path, 'file_size' => $file->getSize(), 'fingerprint' => $hash, 'status' => 'queued', 'privacy_mode' => $r->boolean('privacy_mode', true)]);
        ProcessFacebookImport::dispatch($id);

        return response()->json(['data' => ['import_id' => $id, 'status' => 'queued'], 'meta' => [], 'error' => null], 202);
    }

    public function show(Request $r, string $id): JsonResponse
    {
        $import = ImportSession::with('diagnostics')->where('user_id', $r->user()->id)->findOrFail($id);

        return response()->json(['data' => $import, 'meta' => [], 'error' => null]);
    }

    public function destroy(Request $r, string $id, Aggregator $aggregator): JsonResponse
    {
        $import = ImportSession::where('user_id', $r->user()->id)->findOrFail($id);
        Storage::deleteDirectory('private/imports/'.$id);
        $import->delete();
        SocialPerson::where('user_id', $r->user()->id)->doesntHave('conversations')->doesntHave('friendships')->doesntHave('sourceInteractions')->doesntHave('targetInteractions')->delete();
        $aggregator->rebuild($r->user()->id);

        return response()->json(['data' => ['deleted' => true], 'meta' => [], 'error' => null]);
    }

    public function cancel(Request $r, string $id): JsonResponse
    {
        $import = ImportSession::where('user_id', $r->user()->id)->findOrFail($id);
        abort_unless(in_array($import->status, ['uploaded', 'queued', 'extracting', 'scanning', 'parsing']), 409);
        $import->update(['status' => 'cancelled', 'completed_at' => now()]);

        return response()->json(['data' => ['status' => 'cancelled'], 'meta' => [], 'error' => null]);
    }

    public function retry(Request $r, string $id): JsonResponse
    {
        $import = ImportSession::where('user_id', $r->user()->id)->findOrFail($id);
        abort_unless(in_array($import->status, ['failed', 'cancelled']), 409);
        $import->update(['status' => 'queued', 'completed_at' => null]);
        ProcessFacebookImport::dispatch($id);

        return response()->json(['data' => ['status' => 'queued'], 'meta' => [], 'error' => null], 202);
    }

    public function ownerCandidates(Request $r, string $id): JsonResponse
    {
        $import = ImportSession::where('user_id', $r->user()->id)->findOrFail($id);
        $rows = DB::table('owner_identity_candidates as c')->join('social_people as p', 'p.id', '=', 'c.social_person_id')->where('c.import_session_id', $import->id)->select('p.id', 'p.display_name', 'c.signal', 'c.confidence')->orderByDesc('c.confidence')->get();

        return response()->json(['data' => $rows, 'meta' => ['status' => $import->owner_resolution_status], 'error' => null]);
    }

    public function resolveOwner(Request $r, string $id, OwnerIdentityResolver $resolver, Aggregator $aggregator): JsonResponse
    {
        $import = ImportSession::where('user_id', $r->user()->id)->findOrFail($id);
        $data = $r->validate(['social_person_id' => 'required|uuid']);
        $person = SocialPerson::where('user_id', $r->user()->id)->findOrFail($data['social_person_id']);
        abort_unless(DB::table('owner_identity_candidates')->where('import_session_id', $id)->where('social_person_id', $person->id)->exists(), 422);
        $resolver->resolve($import, $person);
        $aggregator->rebuild($r->user()->id);

        return response()->json(['data' => ['status' => 'resolved', 'owner' => $person], 'meta' => [], 'error' => null]);
    }
}
