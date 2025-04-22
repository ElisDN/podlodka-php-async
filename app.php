<?php

declare(strict_types=1);

namespace demo;

use Closure;

final class Loop
{
    private static array $tasks = [];

    public static function enqueue(Closure $task): void
    {
        self::$tasks[] = $task;
    }

    public static function run(): void
    {
        while ($task = array_shift(self::$tasks)) {
            $task();
        }
    }
}

Loop::enqueue(function () {
    echo 'Begin' . PHP_EOL;

    echo date('Y-m-d H:i:s') . PHP_EOL;

    $start = time();
    $timeout = 3;
    $callback = function () {
        echo date('Y-m-d H:i:s') . PHP_EOL;
    };

    $task = function () use ($start, $timeout, $callback, &$task) {
        $now = time();

        if ($now >= $start + $timeout) {
            $callback();
        } else {
            Loop::enqueue($task);
        }
    };

    Loop::enqueue($task);

    echo 'End' . PHP_EOL;
});

Loop::run();
