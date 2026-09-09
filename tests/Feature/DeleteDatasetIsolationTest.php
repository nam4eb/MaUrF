<?php

namespace Tests\Feature;

use App\Models\ImportSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeleteDatasetIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletion_does_not_touch_another_user(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        foreach ([$a, $b] as $u) {
            ImportSession::create(['id' => (string) Str::uuid(), 'user_id' => $u->id, 'original_filename' => 'x.json', 'storage_path' => 'private/x', 'file_size' => 2, 'fingerprint' => hash('sha256', (string) $u->id), 'status' => 'completed', 'privacy_mode' => true]);
        }$this->actingAs($a)->deleteJson('/api/analytics/data')->assertOk();
        $this->assertDatabaseMissing('import_sessions', ['user_id' => $a->id]);
        $this->assertDatabaseHas('import_sessions', ['user_id' => $b->id]);
    }
}
