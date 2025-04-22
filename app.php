<?php

declare(strict_types=1);

namespace demo;

use Closure;

$task1 = function () {
    echo '1.1' . PHP_EOL;

    return function () {
        echo '1.2' . PHP_EOL;
    };
};

$task2 = function () {
    echo '2.1' . PHP_EOL;

    return function () {
        echo '2.2' . PHP_EOL;
    };
};

$tasks = [
    $task1,
    $task2,
];

foreach ($tasks as $task) {
    $result = $task();

    if ($result instanceof Closure) {
        $result();
    }
}
