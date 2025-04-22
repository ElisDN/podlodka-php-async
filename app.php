<?php

declare(strict_types=1);

namespace demo;

use Closure;

final class Loop
{
    private static bool $active = false;
    private static int $tasksCounter = 0;
    private static array $tasks = [];

    public static function enqueue(Closure $task): void
    {
        self::$tasks[] = $task;
    }

    public static function run(): void
    {
        self::$active = true;

        while (self::$active && $task = array_shift(self::$tasks)) {
            $task();
            self::interruptIfNeedle();
        }
    }

    public static function stop(): void
    {
        self::$active = false;
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

function interval(int $timeout, Closure $callback): void
{
    $task = function () use ($timeout, $callback, &$task) {
        $callback();
        timeout($timeout, $task);
    };

    timeout($timeout, $task);
}

Loop::enqueue(function () {
    echo 'Begin' . PHP_EOL;

    timeout(5, function () {
        echo 'Hello' . PHP_EOL;
    });

    interval(3, function () {
        echo 'World' . PHP_EOL;
    });

    interval(1, function () {
        echo date('Y-m-d H:i:s') . PHP_EOL;
    });

    echo 'End' . PHP_EOL;
});

pcntl_async_signals(true);

$signalHandler = function () {
    Loop::stop();
};

pcntl_signal(SIGINT, $signalHandler);
pcntl_signal(SIGTERM, $signalHandler);
pcntl_signal(SIGHUP,  $signalHandler);

Loop::run();
