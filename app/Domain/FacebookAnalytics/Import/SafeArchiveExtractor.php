<?php

declare(strict_types=1);

namespace App\Domain\FacebookAnalytics\Import;

use RuntimeException;
use ZipArchive;

final class SafeArchiveExtractor
{
    public function extract(string $archive, string $destination): array
    {
        $zip = new ZipArchive;
        if ($zip->open($archive) !== true) {
            throw new RuntimeException('IMPORT_INVALID_ARCHIVE');
        }
        $files = [];
        $bytes = 0;
        $maxFiles = config('facebook_analytics.max_files');
        $maxBytes = config('facebook_analytics.max_extracted_bytes');
        if ($zip->numFiles > $maxFiles) {
            throw new RuntimeException('IMPORT_ARCHIVE_UNSAFE');
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $s = $zip->statIndex($i);
            $name = str_replace('\\', '/', $s['name']);
            if (str_starts_with($name, '/') || preg_match('#(^|/)\.\.(/|$)#', $name) || str_contains($name, "\0")) {
                throw new RuntimeException('IMPORT_ARCHIVE_UNSAFE');
            }
            $bytes += (int) $s['size'];
            if ($bytes > $maxBytes) {
                throw new RuntimeException('IMPORT_ARCHIVE_UNSAFE');
            }$compressed = max(1, (int) ($s['comp_size'] ?? 1));
            if ((int) $s['size'] / $compressed > config('facebook_analytics.max_compression_ratio')) {
                throw new RuntimeException('IMPORT_ARCHIVE_UNSAFE');
            }$opsys = 0;
            $attrs = 0;
            if ($zip->getExternalAttributesIndex($i, $opsys, $attrs) && (($attrs >> 16) & 0xF000) === 0xA000) {
                throw new RuntimeException('IMPORT_ARCHIVE_UNSAFE');
            }if (! empty($s['encryption_method'])) {
                throw new RuntimeException('IMPORT_ARCHIVE_UNSAFE');
            }if (str_ends_with(strtolower($name), '.json')) {
                $files[] = $name;
            }
        }
        if (! is_dir($destination)) {
            mkdir($destination, 0700, true);
        }
        foreach ($files as $name) {
            $target = $destination.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $name);
            if (! is_dir(dirname($target))) {
                mkdir(dirname($target), 0700, true);
            }$in = $zip->getStream($name);
            $out = fopen($target, 'wb');
            stream_copy_to_stream($in, $out);
            fclose($in);
            fclose($out);
        } $zip->close();

        return $files;
    }
}
