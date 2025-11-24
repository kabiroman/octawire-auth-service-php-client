<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client;

use Grpc\RpcException;
use Grpc\Status;
use Kabiroman\Octawire\AuthService\Client\Exception\AuthException;
use Kabiroman\Octawire\AuthService\Client\Exception\ConnectionException;
use Kabiroman\Octawire\AuthService\Client\Exception\InvalidTokenException;
use Kabiroman\Octawire\AuthService\Client\Exception\RateLimitException;
use Kabiroman\Octawire\AuthService\Client\Exception\TokenExpiredException;
use Kabiroman\Octawire\AuthService\Client\Exception\TokenRevokedException;

/**
 * Обработчик ошибок - оборачивает gRPC ошибки в понятные типы
 */
class ErrorHandler
{
    /**
     * Обертка gRPC ошибок в понятные типы исключений
     *
     * @param \Throwable $err Исходная ошибка
     * @return AuthException Обернутое исключение
     */
    public static function wrapError(\Throwable $err): AuthException
    {
        // Если это уже наше исключение, возвращаем как есть
        if ($err instanceof AuthException) {
            return $err;
        }

        // Обработка gRPC ошибок
        if ($err instanceof RpcException) {
            return self::wrapRpcError($err);
        }

        // Обработка обычных исключений
        $message = $err->getMessage();
        $code = $err->getCode();

        // Проверка на ошибки подключения
        if (str_contains(strtolower($message), 'connection') ||
            str_contains(strtolower($message), 'connect') ||
            str_contains(strtolower($message), 'unreachable')) {
            return new ConnectionException($message, $code, $err);
        }

        // По умолчанию возвращаем базовое исключение
        return new AuthException($message, $code, $err);
    }

    /**
     * Обертка gRPC RpcException
     */
    private static function wrapRpcError(RpcException $err): AuthException
    {
        $code = $err->getCode();
        $message = $err->getMessage();
        $details = $err->getMetadata() ?? [];

        // Извлекаем информацию о rate limit из metadata
        if ($code === Status::RESOURCE_EXHAUSTED) {
            $limit = $details['x-ratelimit-limit'][0] ?? null;
            $remaining = $details['x-ratelimit-remaining'][0] ?? null;
            $window = $details['x-ratelimit-window'][0] ?? null;

            return new RateLimitException(
                $message,
                $code,
                $err,
                $limit !== null ? (int)$limit : null,
                $remaining !== null ? (int)$remaining : null,
                $window !== null ? (int)$window : null
            );
        }

        // Обработка ошибок токенов
        if (str_contains(strtolower($message), 'expired')) {
            return new TokenExpiredException($message, $code, $err, $details);
        }

        if (str_contains(strtolower($message), 'revoked')) {
            return new TokenRevokedException($message, $code, $err, $details);
        }

        if (str_contains(strtolower($message), 'invalid') ||
            str_contains(strtolower($message), 'malformed')) {
            return new InvalidTokenException($message, $code, $err, $details);
        }

        // Обработка ошибок подключения
        if ($code === Status::UNAVAILABLE || $code === Status::DEADLINE_EXCEEDED) {
            return new ConnectionException($message, $code, $err);
        }

        // По умолчанию возвращаем базовое исключение
        return new AuthException($message, $code, $err, (string)$code, $details);
    }
}

