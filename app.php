<?php

declare(strict_types=1);

namespace demo;

use Closure;
use Exception;
use RuntimeException;

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

        while (self::$active && count(self::$tasks) > 0) {
            self::doNext();
        }
    }

    public static function doNext(): void
    {
        if ($task = array_shift(self::$tasks)) {
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

function fetch(string $url, Closure $onSuccess, ?Closure $onError = null): void
{
    Loop::enqueue(function () use ($url, $onSuccess, $onError) {
        $segments = parse_url($url);

        $stream = fsockopen($segments['host'], $segments['port'] ?? 80, $errorCode, $errorMessage, 3);

        if (!$stream) {
            $onError(new RuntimeException("$errorMessage ($errorCode)"));
        } else {
            stream_set_blocking($stream, false);

            $uri = ($segments['path'] ?? '/') . (!empty($segments['query']) ? '?' . $segments['query'] : '');
            $request = "GET $uri HTTP/1.1\r\n";
            $request .= "Host: {$segments['host']}\r\n";
            $request .= "Connection: Close\r\n\r\n";

            fwrite($stream, $request);

            $buffer = '';

            $task = function () use ($stream, &$buffer, $onSuccess, &$task) {
                if (!feof($stream)) {
                    $buffer .= fgets($stream, 128);
                    Loop::enqueue($task);
                } else {
                    fclose($stream);
                    [, $body] = explode("\r\n\r\n", $buffer);
                    $onSuccess($body);
                }
            };

            Loop::enqueue($task);
        }
    });
}

Loop::enqueue(function () {
    echo 'Begin' . PHP_EOL;

    timeout(2, function () {
        echo 'Hello' . PHP_EOL;
    });

    interval(1, function () {
        echo date('Y-m-d H:i:s') . PHP_EOL;
    });

    $resolved = false;
    $success = false;
    $value = null;
    $error = null;

    fetch('http://weather',
        function (string $body) use (&$resolved, &$success, &$value) {
            $resolved = true;
            $success = true;
            $value = $body;
        },
        function (Exception $exception) use (&$resolved, &$success, &$error) {
            $resolved = true;
            $success = false;
            $error = $exception;
        }
    );

    while (!$resolved) {
        Loop::doNext();
    }

    if ($success) {
        echo 'Weather: Given ' . $value . PHP_EOL;
    } else {
        echo 'Weather: Error ' . $error->getMessage() . PHP_EOL;
    }

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
