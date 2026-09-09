<?php

namespace Tests\Unit;

use App\Domain\FacebookAnalytics\Export\OpenXmlWorkbook;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class OpenXmlWorkbookTest extends TestCase
{
    public function test_it_creates_a_real_xlsx_package(): void
    {
        $path = sys_get_temp_dir().'/workbook-'.bin2hex(random_bytes(4)).'.xlsx';
        $book = new OpenXmlWorkbook;
        $book->addSheet('Summary', ['Metric', 'Value'], [['Messages', 3]]);
        $book->save($path);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $this->assertNotFalse($zip->locateName('xl/workbook.xml'));
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringNotContainsString('private fixture text', $xml);
        $zip->close();
        unlink($path);
    }
}
