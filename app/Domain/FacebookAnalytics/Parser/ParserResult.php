<?php

declare(strict_types=1);

namespace App\Domain\FacebookAnalytics\Parser;

final class ParserResult
{
    public function __construct(public string $schema, public int $confidence, public int $seen = 0, public int $imported = 0, public int $skipped = 0, public array $warnings = [], public array $datasets = [], public array $context = []) {}
}
