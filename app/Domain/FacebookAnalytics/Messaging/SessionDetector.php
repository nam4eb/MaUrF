<?php

declare(strict_types=1);

namespace App\Domain\FacebookAnalytics\Messaging;

use Carbon\CarbonInterface;

final class SessionDetector
{
    public function startsNew(?CarbonInterface $previous, CarbonInterface $current): bool
    {
        return ! $previous || $previous->diffInHours($current, true) >= config('facebook_analytics.conversation_gap_hours', 8);
    }
}
