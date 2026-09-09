<?php

declare(strict_types=1);

namespace App\Domain\FacebookAnalytics\Parser;

final class ParserRegistry
{
    public function __construct(private iterable $parsers) {}

    public function matching(string $path): ?FacebookDataParser
    {
        $best = null;
        $confidence = 0;
        foreach ($this->parsers as $parser) {
            if ($parser->supports($path) && $parser->confidence($path) > $confidence) {
                $best = $parser;
                $confidence = $parser->confidence($path);
            }
        }

return $best;
    }
}
