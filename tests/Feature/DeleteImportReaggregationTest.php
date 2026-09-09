<?php

namespace Tests\Feature;

use App\Domain\FacebookAnalytics\Analytics\Aggregator;
use App\Models\Conversation;
use App\Models\ImportSession;
use App\Models\InteractionEdge;
use App\Models\Message;
use App\Models\SocialPerson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeleteImportReaggregationTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_one_import_preserves_and_reaggregates_the_other(): void
    {
        $user = User::factory()->create();
        $owner = $this->person($user, 'Owner');
        $other = $this->person($user, 'Other');
        $first = $this->import($user, 'first');
        $second = $this->import($user, 'second');
        $conversationA = $this->conversation($user, $first, $owner, $other, 'a');
        $conversationB = $this->conversation($user, $second, $owner, $other, 'b');
        $this->message($user, $first, $conversationA, $owner, 'a1', now()->subDays(10));
        $this->message($user, $first, $conversationA, $other, 'a2', now()->subDays(10)->addMinute());
        $this->message($user, $second, $conversationB, $other, 'b1', now()->subDay());

        app(Aggregator::class)->rebuild($user->id);
        $this->assertSame(3, (int) InteractionEdge::firstOrFail()->messages_sent + (int) InteractionEdge::firstOrFail()->messages_received);

        $this->actingAs($user)->deleteJson('/api/imports/'.$first->id)->assertOk();

        $this->assertDatabaseHas('import_sessions', ['id' => $second->id]);
        $this->assertDatabaseHas('messages', ['source_import_id' => $second->id]);
        $edge = InteractionEdge::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(1, (int) $edge->messages_sent + (int) $edge->messages_received);
    }

    private function person(User $user, string $name): SocialPerson
    {
        return SocialPerson::create(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'external_identifier' => strtolower($name), 'display_name' => $name, 'normalized_name' => strtolower($name), 'source' => 'messenger']);
    }

    private function import(User $user, string $name): ImportSession
    {
        return ImportSession::create(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'original_filename' => "$name.json", 'storage_path' => "private/$name.json", 'file_size' => 2, 'fingerprint' => hash('sha256', $name), 'status' => 'completed', 'privacy_mode' => true, 'owner_resolution_status' => 'resolved']);
    }

    private function conversation(User $user, ImportSession $import, SocialPerson $owner, SocialPerson $other, string $suffix): Conversation
    {
        $conversation = Conversation::create(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'external_identifier' => "conversation-$suffix", 'title' => $other->display_name, 'type' => 'direct', 'participant_count' => 2, 'source_import_id' => $import->id]);
        $conversation->participants()->attach([$owner->id => ['is_owner' => true], $other->id => ['is_owner' => false]]);

        return $conversation;
    }

    private function message(User $user, ImportSession $import, Conversation $conversation, SocialPerson $sender, string $fingerprint, $time): void
    {
        Message::create(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'conversation_id' => $conversation->id, 'sender_person_id' => $sender->id, 'source_fingerprint' => hash('sha256', $fingerprint), 'sent_at' => $time, 'source_import_id' => $import->id]);
    }
}
