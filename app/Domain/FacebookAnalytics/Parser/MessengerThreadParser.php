<?php

declare(strict_types=1);

namespace App\Domain\FacebookAnalytics\Parser;

use App\Domain\FacebookAnalytics\Import\PersonIdentityResolver;
use App\Models\Conversation;
use App\Models\ImportSession;
use App\Models\Message;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class MessengerThreadParser implements FacebookDataParser
{
    public function __construct(private StreamingJsonReader $reader, private PersonIdentityResolver $people) {}

    public function name(): string
    {
        return 'MessengerThreadParser';
    }

    public function supports(string $path): bool
    {
        return JsonProbe::has($path, ['participants', 'messages']);
    }

    public function schemaVersion(string $path): string
    {
        return 'messenger-thread-v1';
    }

    public function confidence(string $path): int
    {
        return $this->supports($path) ? 95 : 0;
    }

    public function parse(string $path, ImportSession $import): ParserResult
    {
        $sample = JsonProbe::sample($path);
        preg_match('/"title"\s*:\s*"((?:\\\\.|[^"\\\\])*)"/u', $sample, $m);
        $title = isset($m[1]) ? (json_decode('"'.$m[1].'"') ?: 'Untitled conversation') : 'Untitled conversation';
        $participants = [];
        foreach ($this->reader->items($path, 'participants') as $p) {
            if (! is_array($p)) {
                continue;
            }$name = (string) ($p['name'] ?? 'Unknown participant');
            $participants[$name] = $this->people->resolve($import->user_id, 'messenger', $name, $p['id'] ?? null);
        }
        $external = hash('sha256', 'messenger-thread|'.str_replace('\\', '/', preg_replace('#^.*?(inbox|archived_threads)[/\\\\]#i', '$1/', $path)));
        $conversation = Conversation::updateOrCreate(['user_id' => $import->user_id, 'external_identifier' => $external], ['title' => $title, 'type' => count($participants) > 2 ? 'group' : (count($participants) === 2 ? 'direct' : 'unknown'), 'participant_count' => count($participants), 'source_import_id' => $import->id]);
        foreach ($participants as $person) {
            $conversation->participants()->syncWithoutDetaching([$person->id => ['is_owner' => $import->owner_person_id === $person->id]]);
        }
        $seen = 0;
        $inserted = 0;
        $skipped = 0;
        $first = null;
        $last = null;
        $batch = [];
        foreach ($this->reader->items($path, 'messages') as $index => $msg) {
            $seen++;
            if (! is_array($msg) || empty($msg['sender_name']) || empty($msg['timestamp_ms'])) {
                $skipped++;

                continue;
            }$name = (string) $msg['sender_name'];
            $sender = $participants[$name] ?? $this->people->resolve($import->user_id, 'messenger', $name, $msg['sender_id'] ?? null);
            try {
                $sent = Carbon::createFromTimestampMs((int) $msg['timestamp_ms'], 'UTC');
            } catch (\Throwable) {
                $skipped++;

                continue;
            }$content = isset($msg['content']) ? (string) $msg['content'] : null;
            $fingerprint = hash('sha256', implode('|', [$external, $msg['timestamp_ms'], $name, $msg['type'] ?? 'generic', $content ?? '', json_encode([$msg['photos'] ?? null, $msg['videos'] ?? null, $msg['audio_files'] ?? null], JSON_UNESCAPED_UNICODE)]));
            $batch[] = ['id' => (string) Str::uuid(), 'user_id' => $import->user_id, 'conversation_id' => $conversation->id, 'sender_person_id' => $sender->id, 'source_fingerprint' => $fingerprint, 'sent_at' => $sent, 'message_type' => $msg['type'] ?? 'generic', 'has_text' => $content !== null, 'has_media' => isset($msg['photos']) || isset($msg['videos']) || isset($msg['audio_files']), 'has_reaction' => ! empty($msg['reactions']), 'content' => $import->privacy_mode ? null : $content, 'content_hash' => $content !== null ? hash('sha256', $content) : null, 'metadata' => json_encode(['is_unsent' => (bool) ($msg['is_unsent'] ?? false)]), 'source_import_id' => $import->id, 'created_at' => now(), 'updated_at' => now()];
            $first = $first ? min($first, $sent) : $sent;
            $last = $last ? max($last, $sent) : $sent;
            if (count($batch) >= 500) {
                $inserted += $this->flush($batch);
                $batch = [];
            }
        }$inserted += $this->flush($batch);
        $conversation->update(['message_count' => Message::where('conversation_id', $conversation->id)->count(), 'first_message_at' => $first, 'last_message_at' => $last]);

        return new ParserResult($this->schemaVersion($path), 95, $seen, $inserted, $skipped, [], ['messenger']);
    }

    private function flush(array $rows): int
    {
        if (! $rows) {
            return 0;
        }

return DB::table('messages')->insertOrIgnore($rows);
    }
}
