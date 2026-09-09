<?php

namespace Tests\Feature;

use App\Jobs\ProcessFacebookImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_is_owned_queued_and_privacy_first(): void
    {
        Storage::fake('local');
        Queue::fake();
        $u = User::factory()->create();
        $response = $this->actingAs($u)->postJson('/api/imports', ['archive' => UploadedFile::fake()->createWithContent('message.json', '{"participants":[],"messages":[]}'), 'privacy_mode' => true]);
        $response->assertAccepted()->assertJsonPath('data.status', 'queued');
        $this->assertDatabaseHas('import_sessions', ['user_id' => $u->id, 'privacy_mode' => 1]);
        Queue::assertPushed(ProcessFacebookImport::class);
    }
}
