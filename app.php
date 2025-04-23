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

function fetch(string $url, ?Closure $onSuccess = null, ?Closure $onError = null): Promise
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
    }, $onSuccess, $onError);
}

final class Promise
{
    private ?Closure $onSuccess;
    private ?Closure $onError;
    private bool $resolved = false;
    private bool $success = false;
    private mixed $value = null;
    private ?Exception $error = null;
    private array $children = [];

    public function __construct(Closure $task, ?Closure $onSuccess = null, ?Closure $onError = null)
    {
        $this->onSuccess = $onSuccess;
        $this->onError = $onError;

        try {
            $task($this->resolve(...), $this->reject(...));
        } catch (Exception $exception) {
            $this->reject($exception);
        }
    }

    public function then(Closure $onSuccess, ?Closure $onError = null): self
    {
        return $this->addChild(new self(fn () => null, $onSuccess, $onError));
    }

    public function catch(Closure $onError): self
    {
        return $this->addChild(new self(fn () => null, null, $onError));
    }

    private function addChild(self $child): self
    {
        $this->children[] = $child;

        if ($this->isResolved() && $this->isSuccess()) {
            $child->resolve($this->value);
        }

        if ($this->isResolved() && !$this->isSuccess()) {
            $child->reject($this->error);
        }

        return $child;
    }

    private function resolve(mixed $value): void
    {
        if ($this->isResolved()) {
            return;
        }

        if ($this->onSuccess !== null) {
            try {
                $value = ($this->onSuccess)($value);
            } catch (Exception $exception) {
                $this->reject($exception);
                return;
            }
        }

        $this->resolved = true;
        $this->success = true;
        $this->value = $value;

        foreach ($this->children as $child) {
            $child->resolve($this->value);
        }
    }

    private function reject(Exception $error): void
    {
        if ($this->isResolved()) {
            return;
        }

        if ($this->onError !== null) {
            try {
                $value = ($this->onError)($error);

                $this->resolved = true;
                $this->success = true;
                $this->value = $value;
    
                foreach ($this->children as $child) {
                    $child->resolve($this->value);
                }

                return;
            } catch (Exception $exception) {
                $error = $exception;
            }
        }

        $this->resolved = true;
        $this->success = false;
        $this->error = $error;

        foreach ($this->children as $child) {
            $child->reject($this->error);
        }
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

function all(array $promises): Promise
{
    return new Promise(function (Closure $resolve, Closure $reject) use ($promises) {
        try {
            $values = array_map(fn (Promise $promise) => await($promise), $promises);
            $resolve($values);
        } catch (Exception $error) {
            $reject($error);
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

    echo 'Fetch Callbacks' . PHP_EOL;

    fetch('http://weather', function (string $body) {
        echo 'Weather: Callback Given ' . $body . PHP_EOL;
    }, function (Exception $error) {
        echo 'Weather: Callback Error ' . $error->getMessage() . PHP_EOL;
    });

    echo 'Fetch Await Callbacks' . PHP_EOL;

    $data = await(fetch('http://weather', function (string $body) {
        return json_decode($body, true, JSON_THROW_ON_ERROR);
    }, function () {
        return [];
    }));

    echo 'Weather: Await Callback Process ' . print_r($data, true) . PHP_EOL;

    echo 'Fetch Then-Catch' . PHP_EOL;

    fetch('http://weather')
        ->then(function (string $body) {
            echo 'Weather: Then Given ' . $body . PHP_EOL;
            return json_decode($body, true, JSON_THROW_ON_ERROR);
        })
        ->then(function (array $data) {
            echo 'Weather: Then Process ' . print_r($data, true) . PHP_EOL;
        })
        ->catch(function (Exception $error) {
            echo 'Weather: Catch Error ' . $error->getMessage() . PHP_EOL;
        });

    echo 'Fetch Await Then-Catch' . PHP_EOL;

    $data = await(fetch('http://weather')
        ->then(fn (string $body) => json_decode($body, true, JSON_THROW_ON_ERROR))
        ->catch(fn ()  => []));

    echo 'Weather: Await Then-Catch Process ' . print_r($data, true) . PHP_EOL;

    echo 'Fetch Await' . PHP_EOL;

    try {
        $body = await(fetch('http://weather'));
        echo 'Weather: Await Given ' . $body . PHP_EOL;
    } catch (Exception $exception) {
        echo 'Weather: Await Error ' . $exception->getMessage() . PHP_EOL;
    }

    echo 'Fetch Await All' . PHP_EOL;

    try {
        [$body1, $body2, $body3] = await(all([
            fetch('http://weather/?day=1'),
            fetch('http://weather/?day=2'),
            fetch('http://weather/?day=3'),
        ]));

        echo 'Weather: Await All Given 1 ' . $body1 . PHP_EOL;
        echo 'Weather: Await All Given 2 ' . $body2 . PHP_EOL;
        echo 'Weather: Await All Given 3 ' . $body3 . PHP_EOL;
    } catch (Exception $exception) {
        echo 'Weather: Await All Error ' . $exception->getMessage() . PHP_EOL;
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
