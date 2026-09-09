<?php

namespace Tests\Unit;

use App\Domain\FacebookAnalytics\Messaging\SessionDetector;
use Carbon\Carbon;
use Tests\TestCase;

class SessionDetectorTest extends TestCase
{
    public function test_eight_hour_gap_starts_a_session(): void
    {
        $d = new SessionDetector;
        $this->assertFalse($d->startsNew(Carbon::parse('2026-01-01 10:00'), Carbon::parse('2026-01-01 10:17')));
        $this->assertTrue($d->startsNew(Carbon::parse('2026-01-01 10:00'), Carbon::parse('2026-01-01 18:00')));
    }
}
