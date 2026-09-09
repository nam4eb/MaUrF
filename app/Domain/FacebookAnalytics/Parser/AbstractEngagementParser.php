<?php

declare(strict_types=1);

namespace App\Domain\FacebookAnalytics\Parser;

use App\Domain\FacebookAnalytics\Import\PersonIdentityResolver;
use App\Models\FacebookInteraction;
use App\Models\ImportSession;
use Carbon\Carbon;
use Illuminate\Support\Str;

abstract class AbstractEngagementParser implements FacebookDataParser
{
    public function __construct(protected StreamingJsonReader $reader, protected PersonIdentityResolver $people) {}

    abstract protected function roots(): array;

    abstract protected function interactionType(): string;

    public function name(): string
    {
        return class_basename(static::class);
    }

    public function supports(string $path): bool
    {
        return $this->root($path) !== null;
    }

    public function schemaVersion(string $path): string
    {
        return strtolower($this->interactionType()).'-'.($this->root($path) ?? 'unknown');
    }

    public function confidence(string $path): int
    {
        return $this->supports($path) ? 80 : 0;
    }

    public function parse(string $path, ImportSession $import): ParserResult
    {
        $root = $this->root($path);
        if (! $root) {
            return new ParserResult('unknown', 0, 0, 0, 0, ['Unsupported engagement structure']);
        }$seen = 0;
        $added = 0;
        $skipped = 0;
        foreach ($this->reader->items($path, $root) as $index => $row) {
            $seen++;
            if (! is_array($row)) {
                $skipped++;

                continue;
            }$timestamp = $row['timestamp'] ?? data_get($row, 'timestamp_ms');
            $occurred = null;
            if ($timestamp) {
                $numeric = (int) $timestamp;
                $occurred = $numeric > 9999999999 ? Carbon::createFromTimestampMs($numeric, 'UTC') : Carbon::createFromTimestamp($numeric, 'UTC');
            }$actor = data_get($row, 'actor') ?? data_get($row, 'name') ?? data_get($row, 'data.0.name');
            $actorId = data_get($row, 'actor_id') ?? data_get($row, 'uri');
            $source = $actor ? $this->people->resolve($import->user_id, 'facebook', (string) $actor, $actorId) : null;
            $objectId = (string) (data_get($row, 'id') ?? data_get($row, 'uri') ?? data_get($row, 'title') ?? '');
            $fp = hash('sha256', implode('|', [$this->interactionType(), $timestamp ?? '', (string) $actorId, (string) $actor, $objectId, json_encode($row['data'] ?? null, JSON_UNESCAPED_UNICODE)]));
            $created = FacebookInteraction::firstOrCreate(['user_id' => $import->user_id, 'fingerprint' => $fp], ['id' => (string) Str::uuid(), 'source_person_id' => $source?->id, 'target_person_id' => $import->owner_person_id, 'interaction_type' => $this->interactionType(), 'object_type' => $row['object_type'] ?? rtrim($root, '_v2'), 'object_identifier' => $objectId ?: null, 'occurred_at' => $occurred, 'weight' => config('facebook_analytics.facebook_weights.'.$this->interactionType(), 1), 'metadata' => ['source_root' => $root], 'source_import_id' => $import->id]);
            if ($created->wasRecentlyCreated) {
                $added++;
            }
        }

        $dataset = match ($this->interactionType()) {
            'comment' => 'comments',
            'reaction' => 'reactions',
            'mention' => 'mentions',
            'tag' => 'tags',
            'post' => 'posts',
            default => $this->interactionType(),
        };

        return new ParserResult($this->schemaVersion($path), 80, $seen, $added, $skipped, [], [$dataset]);
    }

    private function root(string $path): ?string
    {
        $sample = JsonProbe::sample($path);
        foreach ($this->roots() as $key) {
            if (str_contains($sample, '"'.$key.'"')) {
                return $key;
            }
        }

        return null;
    }
}
