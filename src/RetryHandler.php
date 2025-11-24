<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client;

use Grpc\Status;
use Kabiroman\Octawire\AuthService\Client\Exception\AuthException;
use Kabiroman\Octawire\AuthService\Client\Exception\ConnectionException;

/**
 * Обработчик retry логики с экспоненциальным backoff
 */
class RetryHandler
{
    private RetryConfig $config;

    public function __construct(?RetryConfig $config = null)
    {
        $this->config = $config ?? new RetryConfig();
    }

    /**
     * Выполнение функции с retry логикой
     *
     * @param callable $fn Функция для выполнения
     * @param array $options Дополнительные опции (max_attempts, initial_backoff, max_backoff)
     * @return mixed Результат выполнения функции
     * @throws AuthException
     */
    public function execute(callable $fn, array $options = []): mixed
    {
        $maxAttempts = $options['max_attempts'] ?? $this->config->maxAttempts;
        $initialBackoff = $options['initial_backoff'] ?? $this->config->initialBackoff;
        $maxBackoff = $options['max_backoff'] ?? $this->config->maxBackoff;

        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return $fn();
            } catch (\Exception $e) {
                $lastException = $e;

                // Если это не retryable ошибка, пробрасываем сразу
                if (!$this->isRetryable($e)) {
                    throw $e;
                }

                // Если это последняя попытка, пробрасываем исключение
                if ($attempt >= $maxAttempts) {
                    break;
                }

                // Вычисляем задержку с экспоненциальным backoff и jitter
                $backoff = $this->calculateBackoff($attempt, $initialBackoff, $maxBackoff);
                usleep((int)($backoff * 1000000)); // Конвертируем секунды в микросекунды
            }
        }

        // Если дошли сюда, значит все попытки исчерпаны
        throw $lastException ?? new ConnectionException("All retry attempts failed");
    }

    /**
     * Проверка, является ли ошибка retryable
     */
    private function isRetryable(\Exception $e): bool
    {
        // Проверяем gRPC статус код
        if ($e instanceof \Grpc\RpcException) {
            $code = $e->getCode();
            return in_array($code, [
                Status::UNAVAILABLE,
                Status::DEADLINE_EXCEEDED,
                Status::RESOURCE_EXHAUSTED,
            ], true);
        }

        // Проверяем по сообщению об ошибке (fallback)
        $message = strtolower($e->getMessage());
        return str_contains($message, 'unavailable') ||
               str_contains($message, 'deadline') ||
               str_contains($message, 'timeout') ||
               str_contains($message, 'connection');
    }

    /**
     * Вычисление задержки с экспоненциальным backoff и jitter
     */
    private function calculateBackoff(int $attempt, float $initialBackoff, float $maxBackoff): float
    {
        // Экспоненциальный backoff: initial * 2^(attempt-1)
        $backoff = $initialBackoff * (2 ** ($attempt - 1));

        // Ограничиваем максимальным значением
        $backoff = min($backoff, $maxBackoff);

        // Добавляем jitter (случайное значение от 0 до 25% от backoff)
        $jitter = $backoff * 0.25 * (mt_rand() / mt_getrandmax());

        return $backoff + $jitter;
    }
}

