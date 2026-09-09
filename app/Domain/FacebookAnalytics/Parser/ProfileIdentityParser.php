<?php

declare(strict_types=1);

namespace App\Domain\FacebookAnalytics\Parser;

use App\Models\ImportSession;

final class ProfileIdentityParser implements FacebookDataParser
{
    public function name(): string
    {
        return 'ProfileIdentityParser';
    }

    public function supports(string $path): bool
    {
        $name = strtolower(str_replace('\\', '/', $path));

        return (str_contains($name, 'profile_information') || str_contains($name, 'personal_information')) && str_contains(JsonProbe::sample($path), '"name"');
    }

    public function schemaVersion(string $path): string
    {
        return 'profile-identity-v1';
    }

    public function confidence(string $path): int
    {
        return $this->supports($path) ? 95 : 0;
    }

    public function parse(string $path, ImportSession $import): ParserResult
    {
        $sample = JsonProbe::sample($path, 1048576);
        preg_match('/"name"\s*:\s*"((?:\\\\.|[^"\\\\])*)"/u', $sample, $m);
        $name = isset($m[1]) ? json_decode('"'.$m[1].'"') : null;

        return new ParserResult('profile-identity-v1', 95, $name ? 1 : 0, 0, $name ? 0 : 1, $name ? [] : ['Profile name was not readable'], ['profile'], ['profile_name' => $name]);
    }
}
