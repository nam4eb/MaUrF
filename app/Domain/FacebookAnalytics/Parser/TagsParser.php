<?php

namespace App\Domain\FacebookAnalytics\Parser;

final class TagsParser extends AbstractEngagementParser
{
    protected function roots(): array
    {
        return ['tags_v2', 'tags', 'tagged_posts'];
    }

    protected function interactionType(): string
    {
        return 'tag';
    }
}
