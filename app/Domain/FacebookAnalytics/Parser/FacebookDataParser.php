<?php

declare(strict_types=1);

namespace App\Domain\FacebookAnalytics\Parser;

use App\Models\ImportSession;

interface FacebookDataParser
{
    public function name(): string;

    public function supports(string $path): bool;

    public function schemaVersion(string $path): string;

    public function confidence(string $path): int;

    public function parse(string $path, ImportSession $import): ParserResult;
}
