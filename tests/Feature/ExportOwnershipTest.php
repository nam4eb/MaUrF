<?php

namespace Tests\Feature;

use App\Models\AnalyticsExport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExportOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_other_user_cannot_download_private_export(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $id = (string) Str::uuid();
        $path = 'private/exports/'.$id.'.xlsx';
        Storage::put($path, 'private');
        AnalyticsExport::create(['id' => $id, 'user_id' => $owner->id, 'status' => 'completed', 'format' => 'xlsx', 'storage_path' => $path, 'expires_at' => now()->addHour()]);
        $this->actingAs($other)->get('/api/exports/'.$id.'/download')->assertNotFound();
    }
}
