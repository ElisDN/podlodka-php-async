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

function fetch(string $url): Promise
{
    return new Promise(function (Closure $resolve, Closure $reject) use ($url) {
        Loop::enqueue(function () use ($url, $resolve, $reject) {
            $segments = parse_url($url);

            $stream = fsockopen($segments['host'], $segments['port'] ?? 80, $errorCode, $errorMessage, 3);

            if (!$stream) {
                $reject(new RuntimeException("$errorMessage ($errorCode)"));
            } else {
                stream_set_blocking($stream, false);

                $uri = ($segments['path'] ?? '/') . (!empty($segments['query']) ? '?' . $segments['query'] : '');
                $request = "GET $uri HTTP/1.1\r\n";
                $request .= "Host: {$segments['host']}\r\n";
                $request .= "Connection: Close\r\n\r\n";

                fwrite($stream, $request);

                $buffer = '';

                $task = function () use ($stream, &$buffer, $resolve, &$task) {
                    if (!feof($stream)) {
                        $buffer .= fgets($stream, 128);
                        Loop::enqueue($task);
                    } else {
                        fclose($stream);
                        [, $body] = explode("\r\n\r\n", $buffer);
                        $resolve($body);
                    }
                };

                Loop::enqueue($task);
            }
        });
    });
}

final class Promise
{
    private bool $resolved = false;
    private bool $success = false;
    private mixed $value = null;
    private ?Exception $error = null;

    public function __construct(Closure $task)
    {
        try {
            $task($this->resolve(...), $this->reject(...));
        } catch (Exception $exception) {
            $this->reject($exception);
        }
    }

    private function resolve(mixed $value): void
    {
        $this->resolved = true;
        $this->success = true;
        $this->value = $value;
    }

    private function reject(Exception $error): void
    {
        $this->resolved = true;
        $this->success = false;
        $this->error = $error;
    }

    public function isResolved(): bool
    {
        return $this->resolved;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function getError(): Exception
    {
        return $this->error;
    }
}

function await(Promise $promise): mixed
{
    while (!$promise->isResolved()) {
        Loop::doNext();
    }

    if ($promise->isSuccess()) {
        return $promise->getValue();
    }

    throw $promise->getError();
}

Loop::enqueue(function () {
    echo 'Begin' . PHP_EOL;

    timeout(2, function () {
        echo 'Hello' . PHP_EOL;
    });

    interval(1, function () {
        echo date('Y-m-d H:i:s') . PHP_EOL;
    });

    echo 'Fetch Await' . PHP_EOL;

    try {
        $body = await(fetch('http://weather'));
        echo 'Weather: Await Given ' . $body . PHP_EOL;
    } catch (Exception $exception) {
        echo 'Weather: Await Error ' . $exception->getMessage() . PHP_EOL;
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
