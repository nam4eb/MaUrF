<?php

declare(strict_types=1);

namespace App\Domain\FacebookAnalytics\Parser;

final class JsonProbe
{
    public static function sample(string $path, int $bytes = 262144): string
    {
        $h = fopen($path, 'rb');
        if (! $h) {
            return '';
        }$s = fread($h, $bytes) ?: '';
        fclose($h);

        return $s;
    }

    public static function has(string $path, array $keys): bool
    {
        $s = self::sample($path);
        foreach ($keys as $k) {
            if (! str_contains($s, '"'.$k.'"')) {
                return false;
            }
        }

return true;
    }
}
