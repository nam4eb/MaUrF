<?php

namespace App\Domain\FacebookAnalytics\Parser;

final class ReactionsParser extends AbstractEngagementParser
{
    protected function roots(): array
    {
        return ['reactions_v2', 'reactions', 'likes_and_reactions'];
    }

    protected function interactionType(): string
    {
        return 'reaction';
    }
}
