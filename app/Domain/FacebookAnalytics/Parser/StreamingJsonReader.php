<?php

declare(strict_types=1);

namespace App\Domain\FacebookAnalytics\Parser;

use Generator;
use RuntimeException;

final class StreamingJsonReader
{
    /** Streams objects/scalars from a named top-level array without loading the document. */
    public function items(string $path, string $key): Generator
    {
        $h = fopen($path, 'rb');
        if (! $h) {
            throw new RuntimeException('Unable to open JSON file.');
        }try {
            $this->seekKeyArray($h, $key);
            $value = '';
            $depth = 0;
            $string = false;
            $escape = false;
            while (($c = fgetc($h)) !== false) {
                if ($string) {
                    $value .= $c;
                    if ($escape) {
                        $escape = false;
                    } elseif ($c === '\\') {
                        $escape = true;
                    } elseif ($c === '"') {
                        $string = false;
                    }

continue;
                }if ($c === '"') {
                    $string = true;
                    $value .= $c;

                    continue;
                }if ($c === '{' || $c === '[') {
                    $depth++;
                    $value .= $c;

                    continue;
                }if ($c === '}' || $c === ']') {
                    if ($c === ']' && $depth === 0) {
                        if (trim($value) !== '') {
                            yield $this->decode($value);
                        }break;
                    }$depth--;
                    $value .= $c;

                    continue;
                }if ($c === ',' && $depth === 0) {
                    if (trim($value) !== '') {
                        yield $this->decode($value);
                    }$value = '';

                    continue;
                }$value .= $c;
            }
        } finally {
            fclose($h);
        }
    }

    public function value(string $path, string $key): mixed
    {
        foreach ($this->items($path, '__never__') as $_) {
        }

return null;
    }

    private function seekKeyArray($h, string $key): void
    {
        $needle = '"'.$key.'"';
        $window = '';
        while (($c = fgetc($h)) !== false) {
            $window .= $c;
            if (strlen($window) > strlen($needle)) {
                $window = substr($window, -strlen($needle));
            }if ($window === $needle) {
                while (($c = fgetc($h)) !== false && ctype_space($c)) {
                }if ($c !== ':') {
                    continue;
                }while (($c = fgetc($h)) !== false && ctype_space($c)) {
                }if ($c === '[') {
                    return;
                }
            }
        }throw new RuntimeException('JSON array not found: '.$key);
    }

    private function decode(string $json): mixed
    {
        $value = json_decode(trim($json), true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Malformed JSON record: '.json_last_error_msg());
        }

return $value;
    }
}
