<?php

namespace Tests\Feature;

use App\Models\ImportSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ImportCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_cancel_own_queued_import_but_not_anothers(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $import = ImportSession::create(['id' => (string) Str::uuid(), 'user_id' => $a->id, 'original_filename' => 'x.json', 'storage_path' => 'private/x', 'file_size' => 2, 'fingerprint' => hash('sha256', 'cancel'), 'status' => 'queued', 'privacy_mode' => true]);
        $this->actingAs($b)->postJson('/api/imports/'.$import->id.'/cancel')->assertNotFound();
        $this->actingAs($a)->postJson('/api/imports/'.$import->id.'/cancel')->assertOk()->assertJsonPath('data.status', 'cancelled');
    }
}
