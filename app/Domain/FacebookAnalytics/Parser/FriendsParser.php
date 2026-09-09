<?php

declare(strict_types=1);

namespace App\Domain\FacebookAnalytics\Parser;

use App\Domain\FacebookAnalytics\Import\PersonIdentityResolver;
use App\Models\Friendship;
use App\Models\ImportSession;
use Carbon\Carbon;
use Illuminate\Support\Str;

final class FriendsParser implements FacebookDataParser
{
    private const ROOTS = ['friends_v2' => 'current', 'friends' => 'current', 'removed_friends_v2' => 'removed', 'removed_friends' => 'removed', 'received_friend_requests_v2' => 'requested', 'sent_friend_requests_v2' => 'requested', 'friend_requests_v2' => 'requested'];

    public function __construct(private StreamingJsonReader $reader, private PersonIdentityResolver $people) {}

    public function name(): string
    {
        return 'FriendsParser';
    }

    public function supports(string $path): bool
    {
        return $this->root($path) !== null;
    }

    public function schemaVersion(string $path): string
    {
        return 'friends-'.($this->root($path) ?? 'unknown');
    }

    public function confidence(string $path): int
    {
        return $this->supports($path) ? 90 : 0;
    }

    public function parse(string $path, ImportSession $import): ParserResult
    {
        $root = $this->root($path);
        if (! $root) {
            return new ParserResult('unknown', 0, 0, 0, 0, ['Unsupported friends structure']);
        }$status = self::ROOTS[$root];
        $seen = 0;
        $added = 0;
        $skipped = 0;
        foreach ($this->reader->items($path, $root) as $row) {
            $seen++;
            if (! is_array($row) || empty($row['name'])) {
                $skipped++;

                continue;
            }$uri = $row['uri'] ?? $row['profile_uri'] ?? null;
            $person = $this->people->resolve($import->user_id, 'friends', (string) $row['name'], $row['id'] ?? $uri);
            $timestamp = isset($row['timestamp']) ? Carbon::createFromTimestamp((int) $row['timestamp'], 'UTC') : null;
            $fp = hash('sha256', implode('|', ['friend', $status, $row['id'] ?? $uri ?? mb_strtolower((string) $row['name']), $row['timestamp'] ?? '']));
            $model = Friendship::firstOrCreate(['user_id' => $import->user_id, 'fingerprint' => $fp], ['id' => (string) Str::uuid(), 'social_person_id' => $person->id, 'status' => $status, 'friended_at' => $timestamp, 'source_import_id' => $import->id]);
            if ($model->wasRecentlyCreated) {
                $added++;
            }
        }

return new ParserResult($this->schemaVersion($path), 90, $seen, $added, $skipped, [], ['friends']);
    }

    private function root(string $path): ?string
    {
        $sample = JsonProbe::sample($path);
        foreach (array_keys(self::ROOTS) as $key) {
            if (str_contains($sample, '"'.$key.'"')) {
                return $key;
            }
        }

return null;
    }
}
