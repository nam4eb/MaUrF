<?php

namespace App\Domain\FacebookAnalytics\Parser;

final class PostsParser extends AbstractEngagementParser
{
    protected function roots(): array
    {
        return ['posts_v2', 'posts', 'your_posts'];
    }

    protected function interactionType(): string
    {
        return 'post';
    }
}
