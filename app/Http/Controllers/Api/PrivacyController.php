<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrivacyController extends Controller
{
    public function show(Request $r): JsonResponse
    {
        $row = DB::table('privacy_settings')->where('user_id', $r->user()->id)->first() ?? (object) ['privacy_mode' => true, 'retain_archives' => false];

        return response()->json(['data' => $row, 'meta' => [], 'error' => null]);
    }

    public function update(Request $r): JsonResponse
    {
        $data = $r->validate(['privacy_mode' => 'required|boolean', 'retain_archives' => 'required|boolean']);
        DB::table('privacy_settings')->updateOrInsert(['user_id' => $r->user()->id], $data + ['updated_at' => now(), 'created_at' => now()]);

        return $this->show($r);
    }
}
