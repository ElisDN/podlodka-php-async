<?php

declare(strict_types=1);

namespace demo;

echo 'Begin' . PHP_EOL;

echo 1 . PHP_EOL;
echo 2 . PHP_EOL;

echo "Weather: Fetch 1" . PHP_EOL;
$body1 = file_get_contents('http://weather/?day=1');
echo "Weather: Given 1 " . $body1 . PHP_EOL;

echo "Weather: Fetch 2" . PHP_EOL;
$body2 = file_get_contents('http://weather/?day=2');
echo "Weather: Given 2 " . $body2 . PHP_EOL;

echo 3 . PHP_EOL;

for ($i = 1; $i < 5; $i++) {
    echo date('Y-m-d H:i:s') . PHP_EOL;
    sleep(1);
}

echo 4 . PHP_EOL;

echo 'End' . PHP_EOL;
