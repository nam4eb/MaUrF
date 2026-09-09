<?php

declare(strict_types=1);
$count = (int) ($argv[1] ?? 100000);
$path = $argv[2] ?? sys_get_temp_dir().DIRECTORY_SEPARATOR."facebook-$count.json";
$h = fopen($path, 'wb');
fwrite($h, '{"participants":[{"name":"Owner"},{"name":"Synthetic Person"}],"messages":[');
for ($i = 0; $i < $count; $i++) {
    if ($i) {
        fwrite($h, ',');
    }fwrite($h, json_encode(['sender_name' => $i % 2 ? 'Owner' : 'Synthetic Person', 'timestamp_ms' => 1700000000000 + $i * 60000, 'content' => 'Synthetic '.$i, 'type' => 'Generic']));
}fwrite($h, '],"title":"Synthetic Person"}');
fclose($h);
echo $path,PHP_EOL;
