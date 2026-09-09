<?php

declare(strict_types=1);

namespace App\Domain\FacebookAnalytics\Export;

use RuntimeException;
use ZipArchive;

final class OpenXmlWorkbook
{
    private array $sheets = [];

    private string $temp;

    public function __construct()
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP zip extension is required for XLSX export.');
        }$this->temp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'analytics-xlsx-'.bin2hex(random_bytes(8));
        mkdir($this->temp, 0700, true);
    }

    public function addSheet(string $name, array $header, iterable $rows): void
    {
        $index = count($this->sheets) + 1;
        $path = $this->temp.DIRECTORY_SEPARATOR."sheet$index.xml";
        $h = fopen($path, 'wb');
        fwrite($h, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>');
        $number = 1;
        $this->writeRow($h, $number++, $header);
        foreach ($rows as $row) {
            $this->writeRow($h, $number++, is_array($row) ? $row : (array) $row);
        }fwrite($h, '</sheetData></worksheet>');
        fclose($h);
        $safe = str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $name);
        $this->sheets[] = ['name' => mb_substr($safe, 0, 31), 'path' => $path];
    }

    public function save(string $target): void
    {
        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0700, true);
        }$zip = new ZipArchive;
        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create XLSX.');
        }$zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->relations());
        foreach ($this->sheets as $i => $sheet) {
            $zip->addFile($sheet['path'], 'xl/worksheets/sheet'.($i + 1).'.xml');
        }$zip->close();
        $this->cleanup();
    }

    private function writeRow($h, int $number, array $cells): void
    {
        fwrite($h, '<row r="'.$number.'">');
        foreach (array_values($cells) as $i => $value) {
            $ref = $this->column($i + 1).$number;
            $text = htmlspecialchars($value === null ? '' : (string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            fwrite($h, '<c r="'.$ref.'" t="inlineStr"><is><t xml:space="preserve">'.$text.'</t></is></c>');
        }fwrite($h, '</row>');
    }

    private function column(int $n): string
    {
        $s = '';
        while ($n) {
            $n--;
            $s = chr(65 + $n % 26).$s;
            $n = intdiv($n, 26);
        }

return $s;
    }

    private function workbook(): string
    {
        $s = '';
        foreach ($this->sheets as $i => $x) {
            $s .= '<sheet name="'.htmlspecialchars($x['name'], ENT_XML1 | ENT_QUOTES).'" sheetId="'.($i + 1).'" r:id="rId'.($i + 1).'"/>';
        }

return '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$s.'</sheets></workbook>';
    }

    private function relations(): string
    {
        $s = '';
        foreach ($this->sheets as $i => $x) {
            $s .= '<Relationship Id="rId'.($i + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.($i + 1).'.xml"/>';
        }

return '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$s.'</Relationships>';
    }

    private function contentTypes(): string
    {
        $s = '';
        foreach ($this->sheets as $i => $x) {
            $s .= '<Override PartName="/xl/worksheets/sheet'.($i + 1).'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

return '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'.$s.'</Types>';
    }

    private function cleanup(): void
    {
        if (! is_dir($this->temp)) {
            return;
        }foreach (glob($this->temp.DIRECTORY_SEPARATOR.'*') ?: [] as $f) {
            @unlink($f);
        }@rmdir($this->temp);
    }

    public function __destruct()
    {
        $this->cleanup();
    }
}
