<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client;

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
        // Проверяем по сообщению об ошибке
        $message = strtolower($e->getMessage());
        
        // TCP connection errors (retryable)
        if (str_contains($message, 'connection') ||
            str_contains($message, 'connect') ||
            str_contains($message, 'timeout') ||
            str_contains($message, 'closed') ||
            str_contains($message, 'unreachable') ||
            str_contains($message, 'refused')) {
            return true;
        }

        // JATP error codes (check in message format [ERROR_CODE])
        if (preg_match('/^\[([^\]]+)\]/', $message, $matches)) {
            $errorCode = $matches[1];
            // Retryable JATP errors
            return in_array($errorCode, [
                'ERROR_INTERNAL',
                'ERROR_RATE_LIMIT_EXCEEDED', // May be temporary
            ], true);
        }

        // Check error code for connection-related errors
        $code = $e->getCode();
        if ($e instanceof ConnectionException) {
            return true;
        }

        // Socket errors (errno-based)
        // ECONNREFUSED (111), ETIMEDOUT (110), ECONNRESET (104)
        if (in_array($code, [111, 110, 104], true)) {
            return true;
        }

        return false;
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

