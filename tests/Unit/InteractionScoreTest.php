<?php

namespace Tests\Unit;

use App\Domain\FacebookAnalytics\Analytics\InteractionScore;
use PHPUnit\Framework\TestCase;

class InteractionScoreTest extends TestCase
{
    public function test_mutuality_rewards_balance(): void
    {
        $this->assertSame(100.0, InteractionScore::mutuality(100, 100));
        $this->assertSame(10.0, InteractionScore::mutuality(190, 10));
    }

    public function test_recency_decays(): void
    {
        $this->assertSame(100.0, InteractionScore::recency(0));
        $this->assertLessThan(40, InteractionScore::recency(90));
    }

    public function test_frequency_is_log_normalized(): void
    {
        $this->assertSame(100.0, InteractionScore::frequency(100, 100));
        $this->assertGreaterThan(0, InteractionScore::frequency(1, 100));
    }

    public function test_missing_dimensions_are_redistributed(): void
    {
        $this->assertSame(50.0, InteractionScore::overall(['frequency' => 100, 'recency' => 0], ['frequency' => .25, 'recency' => .25, 'facebook' => .5]));
    }
}
