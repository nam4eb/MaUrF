<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DuplicateArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_archive_is_not_counted_twice(): void
    {
        Storage::fake('local');
        Queue::fake();
        $user = User::factory()->create();
        $json = '{"participants":[],"messages":[]}';
        $this->actingAs($user)->post('/api/imports', ['archive' => UploadedFile::fake()->createWithContent('first.json', $json)])->assertAccepted();
        $this->post('/api/imports', ['archive' => UploadedFile::fake()->createWithContent('again.json', $json)])->assertOk()->assertJsonPath('meta.duplicate', true);
        $this->assertDatabaseCount('import_sessions', 1);
    }
}
