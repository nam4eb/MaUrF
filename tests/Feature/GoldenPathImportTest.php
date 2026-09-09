<?php

namespace Tests\Feature;

use App\Jobs\ProcessFacebookImport;
use App\Models\ImportSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class GoldenPathImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_fixture_import_is_private_streamed_and_aggregated(): void
    {
        Storage::fake('local');
        Queue::fake();
        $zip = $this->fixtureZip();
        $user = User::factory()->create();
        $response = $this->actingAs($user)->post('/api/imports', ['archive' => new UploadedFile($zip, 'facebook-export.zip', 'application/zip', null, true), 'privacy_mode' => true]);
        $response->assertAccepted();
        $id = $response->json('data.import_id');
        $job = new ProcessFacebookImport($id);
        app()->call([$job, 'handle']);
        $this->assertDatabaseHas('import_sessions', ['id' => $id, 'status' => 'completed']);
        $this->assertDatabaseHas('friendships', ['user_id' => $user->id, 'status' => 'current']);
        $this->assertDatabaseHas('facebook_interactions', ['user_id' => $user->id, 'interaction_type' => 'reaction']);
        $this->assertDatabaseHas('facebook_interactions', ['user_id' => $user->id, 'interaction_type' => 'comment']);
        $this->assertDatabaseHas('facebook_interactions', ['user_id' => $user->id, 'interaction_type' => 'post']);
        $this->assertDatabaseHas('facebook_interactions', ['user_id' => $user->id, 'interaction_type' => 'mention']);
        $this->assertDatabaseHas('facebook_interactions', ['user_id' => $user->id, 'interaction_type' => 'tag']);
        $coverage = ImportSession::find($id)->data_coverage;
        foreach (['comments', 'reactions', 'posts', 'mentions', 'tags'] as $dataset) {
            $this->assertSame('available', $coverage[$dataset]);
        }
        $this->assertDatabaseMissing('messages', ['user_id' => $user->id, 'content' => 'Chào bạn ❤️']);
        $this->assertSame('resolved', ImportSession::find($id)->owner_resolution_status);
        @unlink($zip);
    }

    private function fixtureZip(): string
    {
        $root = dirname(__DIR__).'/Fixtures/facebook-export-small';
        $path = sys_get_temp_dir().'/fixture-'.bin2hex(random_bytes(4)).'.zip';
        $z = new ZipArchive;
        $z->open($path, ZipArchive::CREATE);
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $z->addFile($file->getPathname(), str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1)));
            }
        }$z->close();

        return $path;
    }
}
