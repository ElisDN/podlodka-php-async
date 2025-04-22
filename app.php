<?php

declare(strict_types=1);

namespace demo;

$code1 = function () {
    echo 1 . PHP_EOL;
};

$code2 = function () {
    echo 2 . PHP_EOL;
};

$code3 = function () {
    echo 3 . PHP_EOL;
};

$code4 = function () {
    echo 4 . PHP_EOL;
};

$tasks = [
    $code1,
    $code2,
    $code3,
    $code4,
];

foreach ($tasks as $task) {
    $task();
}
