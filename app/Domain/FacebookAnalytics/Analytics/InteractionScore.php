<?php

declare(strict_types=1);

namespace App\Domain\FacebookAnalytics\Analytics;

final class InteractionScore
{
    public static function mutuality(int $sent, int $received): float
    {
        return round(100 * (1 - abs($sent - $received) / max($sent + $received, 1)), 2);
    }

    public static function recency(int $days, int $decay = 90): float
    {
        return round(100 * exp(-max(0, $days) / max(1, $decay)), 2);
    }

    public static function frequency(int $value, int $max): float
    {
        return $max < 1 ? 0 : round(100 * log(1 + $value) / log(1 + $max), 2);
    }

    public static function overall(array $scores, array $weights): float
    {
        $available = array_intersect_key($weights, $scores);
        $total = array_sum($available);
        if ($total <= 0) {
            return 0;
        }

        return round(array_sum(array_map(fn ($k) => $scores[$k] * $available[$k] / $total, array_keys($available))), 2);
    }
}
