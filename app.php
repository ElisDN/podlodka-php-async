<?php

declare(strict_types=1);

namespace demo;

use Closure;

final class Loop
{
    private static int $tasksCounter = 0;
    private static array $tasks = [];

    public static function enqueue(Closure $task): void
    {
        self::$tasks[] = $task;
    }

    public static function run(): void
    {
        while ($task = array_shift(self::$tasks)) {
            $task();
            self::interruptIfNeedle();
        }
    }

    private static function interruptIfNeedle(): void
    {
        if (++self::$tasksCounter > 10) {
            self::$tasksCounter = 0;
            usleep(0);
        }
    }
}

function timeout(int $timeout, Closure $callback): void
{
    $start = time();

    $task = function () use ($start, $timeout, $callback, &$task) {
        $now = time();

        if ($now >= $start + $timeout) {
            $callback();
        } else {
            Loop::enqueue($task);
        }
    };

    Loop::enqueue($task);
}

Loop::enqueue(function () {
    echo 'Begin' . PHP_EOL;

    echo date('Y-m-d H:i:s') . PHP_EOL;

    timeout(5, function () {
        echo 'Hello' . PHP_EOL;
    });

    timeout(2, function () {
        echo 'World' . PHP_EOL;
    });

    echo 'End' . PHP_EOL;
});

Loop::run();
