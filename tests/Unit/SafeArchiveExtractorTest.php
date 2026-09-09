<?php

namespace Tests\Unit;

use App\Domain\FacebookAnalytics\Import\SafeArchiveExtractor;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class SafeArchiveExtractorTest extends TestCase
{
    public function test_it_rejects_parent_traversal(): void
    {
        $zip = $this->archive('../escape.json', '{}');
        $this->expectException(RuntimeException::class);
        try {
            (new SafeArchiveExtractor)->extract($zip, sys_get_temp_dir().'/extract-'.bin2hex(random_bytes(3)));
        } finally {
            @unlink($zip);
        }
    }

    public function test_it_rejects_absolute_paths(): void
    {
        $zip = $this->archive('/escape.json', '{}');
        $this->expectException(RuntimeException::class);
        try {
            (new SafeArchiveExtractor)->extract($zip, sys_get_temp_dir().'/extract-'.bin2hex(random_bytes(3)));
        } finally {
            @unlink($zip);
        }
    }

    private function archive(string $name, string $content): string
    {
        $path = sys_get_temp_dir().'/unsafe-'.bin2hex(random_bytes(4)).'.zip';
        $z = new ZipArchive;
        $z->open($path, ZipArchive::CREATE);
        $z->addFromString($name, $content);
        $z->close();

        return $path;
    }
}
