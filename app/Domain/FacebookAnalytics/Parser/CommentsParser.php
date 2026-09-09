<?php

namespace App\Domain\FacebookAnalytics\Parser;

final class CommentsParser extends AbstractEngagementParser
{
    protected function roots(): array
    {
        return ['comments_v2', 'comments', 'comments_and_replies'];
    }

    protected function interactionType(): string
    {
        return 'comment';
    }
}
