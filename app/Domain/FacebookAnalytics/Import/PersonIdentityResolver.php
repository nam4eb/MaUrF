<?php

declare(strict_types=1);

namespace App\Domain\FacebookAnalytics\Import;

use App\Models\SocialPerson;
use Illuminate\Support\Str;

final class PersonIdentityResolver
{
    public function resolve(int $userId, string $source, string $name, ?string $stableId = null, array $metadata = []): SocialPerson
    {
        $normalized = mb_strtolower(trim($name));
        $external = $stableId ? hash('sha256', $source.'|id|'.$stableId) : hash('sha256', $source.'|name|'.$normalized);

        return SocialPerson::firstOrCreate(['user_id' => $userId, 'external_identifier' => $external], ['id' => (string) Str::uuid(), 'display_name' => $name ?: 'Unknown participant', 'normalized_name' => $normalized ?: 'unknown participant', 'source' => $source, 'metadata' => $metadata + ['identity_confidence' => $stableId ? 'high' : 'low']]);
    }
}
