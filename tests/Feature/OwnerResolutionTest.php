<?php

namespace Tests\Feature;

use App\Jobs\ProcessFacebookImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OwnerResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_unresolved_import_can_be_resolved_by_its_owner(): void
    {
        Storage::fake('local');
        Queue::fake();
        $user = User::factory()->create();
        $json = file_get_contents(dirname(__DIR__).'/Fixtures/facebook-export-small/message_1.json');
        $response = $this->actingAs($user)->post('/api/imports', ['archive' => UploadedFile::fake()->createWithContent('message.json', $json), 'privacy_mode' => true])->assertAccepted();
        $id = $response->json('data.import_id');
        app()->call([new ProcessFacebookImport($id), 'handle']);
        $candidates = $this->getJson("/api/imports/$id/owner-candidates")->assertOk()->assertJsonPath('meta.status', 'needs_confirmation')->json('data');
        $owner = collect($candidates)->firstWhere('display_name', 'Demo Analyst');
        $this->postJson("/api/imports/$id/resolve-owner", ['social_person_id' => $owner['id']])->assertOk()->assertJsonPath('data.status', 'resolved');
        $this->assertDatabaseHas('import_sessions', ['id' => $id, 'owner_resolution_status' => 'resolved', 'owner_person_id' => $owner['id']]);
    }
}
