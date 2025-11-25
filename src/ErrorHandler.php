<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client;

use Kabiroman\Octawire\AuthService\Client\Exception\AuthException;
use Kabiroman\Octawire\AuthService\Client\Exception\ConnectionException;
use Kabiroman\Octawire\AuthService\Client\Exception\InvalidTokenException;
use Kabiroman\Octawire\AuthService\Client\Exception\RateLimitException;
use Kabiroman\Octawire\AuthService\Client\Exception\TokenExpiredException;
use Kabiroman\Octawire\AuthService\Client\Exception\TokenRevokedException;

/**
 * Обработчик ошибок - оборачивает JATP и другие ошибки в понятные типы
 */
class ErrorHandler
{
    /**
     * Обертка ошибок в понятные типы исключений
     *
     * @param \Throwable $err Исходная ошибка
     * @param array|null $jatpError JATP error structure (if available)
     * @return AuthException Обернутое исключение
     */
    public static function wrapError(\Throwable $err, ?array $jatpError = null): AuthException
    {
        // Если это уже наше исключение, возвращаем как есть
        if ($err instanceof AuthException) {
            return $err;
        }

        // Обработка JATP ошибок (если передан error structure)
        if ($jatpError !== null) {
            return self::wrapJATPError($jatpError, $err);
        }

        // Обработка ошибок из сообщения (может содержать JATP error code)
        $message = $err->getMessage();
        
        // Проверка на JATP error format: [ERROR_CODE] message
        if (preg_match('/^\[([^\]]+)\]\s*(.+)$/', $message, $matches)) {
            $errorCode = $matches[1];
            $errorMessage = $matches[2];
            return self::mapJATPErrorCode($errorCode, $errorMessage, $err, []);
        }

        // Обработка обычных исключений
        $code = $err->getCode();

        // Проверка на ошибки подключения
        if (str_contains(strtolower($message), 'connection') ||
            str_contains(strtolower($message), 'connect') ||
            str_contains(strtolower($message), 'unreachable') ||
            str_contains(strtolower($message), 'timeout') ||
            str_contains(strtolower($message), 'closed')) {
            return new ConnectionException($message, $code, $err);
        }

        // По умолчанию возвращаем базовое исключение
        return new AuthException($message, $code, $err);
    }

    /**
     * Обертка JATP ошибок
     *
     * @param array $jatpError JATP error structure
     * @param \Throwable|null $previous Previous exception
     * @return AuthException
     */
    private static function wrapJATPError(array $jatpError, ?\Throwable $previous = null): AuthException
    {
        $errorCode = $jatpError['code'] ?? 'ERROR_UNKNOWN';
        $errorMessage = $jatpError['message'] ?? 'Unknown error';
        $details = $jatpError['details'] ?? [];

        return self::mapJATPErrorCode($errorCode, $errorMessage, $previous, $details);
    }

    /**
     * Map JATP error code to exception type
     *
     * @param string $errorCode JATP error code
     * @param string $message Error message
     * @param \Throwable|null $previous Previous exception
     * @param array $details Error details
     * @return AuthException
     */
    private static function mapJATPErrorCode(
        string $errorCode,
        string $message,
        ?\Throwable $previous = null,
        array $details = []
    ): AuthException {
        switch ($errorCode) {
            case 'ERROR_UNAUTHENTICATED':
                return new AuthException($message, 401, $previous, $errorCode, $details);

            case 'ERROR_INVALID_REQUEST':
                // Check if it's token-related
                if (str_contains(strtolower($message), 'token')) {
                    return new InvalidTokenException($message, 400, $previous, $details);
                }
                return new AuthException($message, 400, $previous, $errorCode, $details);

            case 'ERROR_RATE_LIMIT_EXCEEDED':
                $limit = $details['limit'] ?? null;
                $remaining = $details['remaining'] ?? null;
                $window = $details['window'] ?? null;
                return new RateLimitException(
                    $message,
                    429,
                    $previous,
                    $limit !== null ? (int)$limit : null,
                    $remaining !== null ? (int)$remaining : null,
                    $window !== null ? (int)$window : null
                );

            case 'ERROR_KEY_NOT_FOUND':
                return new AuthException($message, 404, $previous, $errorCode, $details);

            case 'ERROR_INTERNAL':
                return new AuthException($message, 500, $previous, $errorCode, $details);

            case 'UNSUPPORTED_PROTOCOL_VERSION':
                return new AuthException($message, 400, $previous, $errorCode, $details);

            default:
                // Try to infer from message content
                $lowerMessage = strtolower($message);
                
                if (str_contains($lowerMessage, 'expired')) {
                    return new TokenExpiredException($message, 401, $previous, $details);
                }

                if (str_contains($lowerMessage, 'revoked')) {
                    return new TokenRevokedException($message, 401, $previous, $details);
                }

                if (str_contains($lowerMessage, 'invalid') || str_contains($lowerMessage, 'malformed')) {
                    if (str_contains($lowerMessage, 'token')) {
                        return new InvalidTokenException($message, 400, $previous, $details);
                    }
                }

                return new AuthException($message, 0, $previous, $errorCode, $details);
        }
    }
}

