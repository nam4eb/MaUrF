<?php

namespace App\Domain\FacebookAnalytics\Parser;

final class MentionsParser extends AbstractEngagementParser
{
    protected function roots(): array
    {
        return ['mentions_v2', 'mentions'];
    }

    protected function interactionType(): string
    {
        return 'mention';
    }
}
