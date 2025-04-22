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
    echo '1.1' . PHP_EOL;

    Loop::enqueue(function () {
        echo '2.1' . PHP_EOL;

        Loop::enqueue(function () {
            echo '2.2' . PHP_EOL;
        });

        echo '2.3' . PHP_EOL;
    });

    echo '1.2' . PHP_EOL;

    Loop::enqueue(function () {
        echo '3.1' . PHP_EOL;

        Loop::enqueue(function ()  {
            echo '3.2' . PHP_EOL;
        });

        echo '3.3' . PHP_EOL;
    });

    echo '1.3' . PHP_EOL;
});

Loop::run();
