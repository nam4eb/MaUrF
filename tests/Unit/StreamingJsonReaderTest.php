<?php

namespace Tests\Unit;

use App\Domain\FacebookAnalytics\Parser\StreamingJsonReader;
use PHPUnit\Framework\TestCase;

class StreamingJsonReaderTest extends TestCase
{
    public function test_it_streams_array_records_with_escaped_content(): void
    {
        $reader = new StreamingJsonReader;
        $rows = iterator_to_array($reader->items(dirname(__DIR__).'/Fixtures/facebook-export-small/message_1.json', 'messages'));
        $this->assertCount(3, $rows);
        $this->assertSame('Chào bạn ❤️', $rows[0]['content']);
    }
}
