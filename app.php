<?php

declare(strict_types=1);

namespace demo;

use Closure;

$tasks = [];

$program = function () use (&$tasks) {
    echo '1.1' . PHP_EOL;

    $tasks[] = function () use (&$tasks) {
        echo '2.1' . PHP_EOL;

        $tasks[] = function () {
            echo '2.2' . PHP_EOL;
        };
    };

    echo '1.2' . PHP_EOL;

    $tasks[] = function () use (&$tasks) {
        echo '3.1' . PHP_EOL;

        $tasks[] = function ()  {
            echo '3.2' . PHP_EOL;
        };
    };

    echo '1.3' . PHP_EOL;
};

$tasks[] = $program;

while ($task = array_shift($tasks)) {
    $results = $task();

    if (is_array($results)) {
        foreach ($results as $result) {
            if ($result instanceof Closure) {
                $tasks[] = $result;
            }
        }
    }
}
