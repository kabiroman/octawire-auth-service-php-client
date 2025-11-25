<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Tests;

use Kabiroman\Octawire\AuthService\Client\RetryConfig;
use Kabiroman\Octawire\AuthService\Client\RetryHandler;
use Kabiroman\Octawire\AuthService\Client\Exception\ConnectionException;
use PHPUnit\Framework\TestCase;

class RetryHandlerTest extends TestCase
{
    public function testSuccessfulExecution(): void
    {
        $handler = new RetryHandler();
        $result = $handler->execute(fn() => 'success');

        $this->assertEquals('success', $result);
    }

    public function testNonRetryableError(): void
    {
        $handler = new RetryHandler();
        $exception = new \RuntimeException('Non-retryable error');

        $this->expectException(\RuntimeException::class);
        $handler->execute(fn() => throw $exception);
    }

    public function testRetryableError(): void
    {
        $handler = new RetryHandler(new RetryConfig([
            'max_attempts' => 3,
            'initial_backoff' => 0.01, // 10ms для быстрого теста
            'max_backoff' => 0.1,
        ]));

        $attempts = 0;
        // Используем ConnectionException, которое распознается как retryable
        $exception = new ConnectionException('Connection timeout');

        try {
            $handler->execute(function () use (&$attempts, $exception) {
                $attempts++;
                if ($attempts < 3) {
                    throw $exception;
                }
                return 'success';
            });
        } catch (\Exception $e) {
            // Если все попытки исчерпаны, должно быть исключение
            $this->fail('Should have succeeded on attempt 3');
        }

        $this->assertEquals(3, $attempts);
    }

    public function testMaxAttempts(): void
    {
        $handler = new RetryHandler(new RetryConfig([
            'max_attempts' => 2,
            'initial_backoff' => 0.01,
        ]));

        $attempts = 0;
        $exception = new ConnectionException('Connection timeout');

        $this->expectException(ConnectionException::class);
        $handler->execute(function () use (&$attempts, $exception) {
            $attempts++;
            throw $exception;
        });

        $this->assertEquals(2, $attempts);
    }
}

