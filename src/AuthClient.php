<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client;

use Kabiroman\Octawire\AuthService\Client\Exception\AuthException;
use Kabiroman\Octawire\AuthService\Client\Exception\ConnectionException;
use Kabiroman\Octawire\AuthService\Client\Transport\JATPClient;

/**
 * Основной клиент для работы с Auth Service через JATP (TCP/JSON)
 */
class AuthClient
{
    private JATPClient $jatpClient;
    private KeyCache $keyCache;
    private Config $config;
    private RetryHandler $retryHandler;

    /**
     * Создание нового клиента
     *
     * @param Config $config Конфигурация клиента
     * @throws ConnectionException
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->keyCache = new KeyCache($config->keyCache);
        $this->retryHandler = new RetryHandler($config->retry);

        // Валидация TLS конфигурации
        $tlsConfig = $config->tcp?->tls ?? $config->tls;
        TLSConfigHelper::validate($tlsConfig);

        // Создание JATP клиента
        try {
            $this->jatpClient = new JATPClient($config);
        } catch (\Exception $e) {
            throw new ConnectionException("Failed to create JATP client: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Закрытие соединения
     */
    public function close(): void
    {
        $this->jatpClient->close();
    }

    /**
     * Получение кэша ключей
     */
    public function getKeyCache(): KeyCache
    {
        return $this->keyCache;
    }

    /**
     * Выполнение JATP запроса с retry логикой
     *
     * @param string $method Метод (например, "JWTService.IssueToken")
     * @param array $payload Полезная нагрузка запроса
     * @param string|null $jwtToken JWT токен для аутентификации
     * @param string|null $serviceName Имя сервиса для межсервисной аутентификации
     * @param string|null $serviceSecret Секрет сервиса для межсервисной аутентификации
     * @return array Ответ от сервера
     * @throws AuthException
     */
    private function call(
        string $method,
        array $payload,
        ?string $jwtToken = null,
        ?string $serviceName = null,
        ?string $serviceSecret = null
    ): array {
        // Добавляем project_id если не указан в payload
        if (!isset($payload['project_id']) && $this->config->projectId !== null) {
            $payload['project_id'] = $this->config->projectId;
        }

        return $this->retryHandler->execute(function () use ($method, $payload, $jwtToken, $serviceName, $serviceSecret) {
            try {
                $response = $this->jatpClient->call($method, $payload, $jwtToken, $serviceName, $serviceSecret);
                return $response;
            } catch (\Exception $e) {
                // ErrorHandler will parse JATP error format from message: [ERROR_CODE] message
                throw ErrorHandler::wrapError($e);
            }
        });
    }

    // JWTService методы

    /**
     * Выдача нового JWT токена (access + refresh)
     *
     * @param array $request Параметры запроса
     * @return array Ответ с токенами
     * @throws AuthException
     */
    public function issueToken(array $request): array
    {
        return $this->call('JWTService.IssueToken', $request);
    }

    /**
     * Выдача межсервисного JWT токена
     *
     * @param array $request Параметры запроса
     * @return array Ответ с токеном
     * @throws AuthException
     */
    public function issueServiceToken(array $request): array
    {
        // Для межсервисных токенов используем service authentication если доступно
        $serviceName = $request['service_name'] ?? null;
        $serviceSecret = $request['service_secret'] ?? null;
        
        // Удаляем из payload, так как они идут в заголовок запроса
        unset($request['service_name'], $request['service_secret']);
        
        return $this->call('JWTService.IssueServiceToken', $request, null, $serviceName, $serviceSecret);
    }

    /**
     * Валидация токена
     *
     * @param array $request Параметры запроса
     * @return array Ответ с результатом валидации
     * @throws AuthException
     */
    public function validateToken(array $request): array
    {
        // ValidateToken требует JWT токен для аутентификации
        // Токен может быть передан в запросе или должен быть в конфиге
        $jwtToken = $request['jwt_token'] ?? $this->config->apiKey;
        unset($request['jwt_token']);
        
        return $this->call('JWTService.ValidateToken', $request, $jwtToken);
    }

    /**
     * Обновление токена
     *
     * @param array $request Параметры запроса
     * @return array Ответ с новым токеном
     * @throws AuthException
     */
    public function refreshToken(array $request): array
    {
        return $this->call('JWTService.RefreshToken', $request);
    }

    /**
     * Отзыв токена
     *
     * @param array $request Параметры запроса
     * @return array Ответ с результатом отзыва
     * @throws AuthException
     */
    public function revokeToken(array $request): array
    {
        $jwtToken = $request['jwt_token'] ?? $this->config->apiKey;
        unset($request['jwt_token']);
        
        return $this->call('JWTService.RevokeToken', $request, $jwtToken);
    }

    /**
     * Парсинг токена без валидации
     *
     * @param array $request Параметры запроса
     * @return array Ответ с claims
     * @throws AuthException
     */
    public function parseToken(array $request): array
    {
        $jwtToken = $request['jwt_token'] ?? $this->config->apiKey;
        unset($request['jwt_token']);
        
        return $this->call('JWTService.ParseToken', $request, $jwtToken);
    }

    /**
     * Извлечение claims из токена
     *
     * @param array $request Параметры запроса
     * @return array Ответ с claims
     * @throws AuthException
     */
    public function extractClaims(array $request): array
    {
        $jwtToken = $request['jwt_token'] ?? $this->config->apiKey;
        unset($request['jwt_token']);
        
        return $this->call('JWTService.ExtractClaims', $request, $jwtToken);
    }

    /**
     * Пакетная валидация токенов
     *
     * @param array $request Параметры запроса
     * @return array Ответ с результатами валидации
     * @throws AuthException
     */
    public function validateBatch(array $request): array
    {
        $jwtToken = $request['jwt_token'] ?? $this->config->apiKey;
        unset($request['jwt_token']);
        
        return $this->call('JWTService.ValidateBatch', $request, $jwtToken);
    }

    /**
     * Получение публичного ключа (с кэшированием)
     *
     * @param array $request Параметры запроса
     * @return array Ответ с публичным ключом
     * @throws AuthException
     */
    public function getPublicKey(array $request): array
    {
        $projectId = $request['project_id'] ?? $this->config->projectId ?? '';
        
        // Проверяем кэш
        if ($projectId !== null && $this->keyCache !== null) {
            $cached = $this->keyCache->get($projectId);
            if ($cached !== null) {
                // Проверяем cache_until
                $cacheUntil = $cached['cache_until'] ?? 0;
                if ($cacheUntil > time()) {
                    return $cached;
                }
            }
        }

        // Запрашиваем у сервера
        $response = $this->call('JWTService.GetPublicKey', $request);

        // Кэшируем результат
        if ($projectId !== null && $this->keyCache !== null && isset($response['cache_until'])) {
            $this->keyCache->set($projectId, $response, $response['cache_until'] - time());
        }

        return $response;
    }

    /**
     * Проверка здоровья сервиса
     *
     * @return array Ответ с информацией о здоровье
     * @throws AuthException
     */
    public function healthCheck(): array
    {
        return $this->call('JWTService.HealthCheck', []);
    }

    // APIKeyService методы

    /**
     * Создание нового API ключа
     *
     * @param array $request Параметры запроса
     * @return array Ответ с созданным API ключом
     * @throws AuthException
     */
    public function createAPIKey(array $request): array
    {
        $jwtToken = $request['jwt_token'] ?? $this->config->apiKey;
        unset($request['jwt_token']);
        
        return $this->call('APIKeyService.CreateAPIKey', $request, $jwtToken);
    }

    /**
     * Валидация API ключа
     *
     * @param array $request Параметры запроса
     * @return array Ответ с результатом валидации
     * @throws AuthException
     */
    public function validateAPIKey(array $request): array
    {
        $jwtToken = $request['jwt_token'] ?? $this->config->apiKey;
        unset($request['jwt_token']);
        
        return $this->call('APIKeyService.ValidateAPIKey', $request, $jwtToken);
    }

    /**
     * Отзыв API ключа
     *
     * @param array $request Параметры запроса
     * @return array Ответ с результатом отзыва
     * @throws AuthException
     */
    public function revokeAPIKey(array $request): array
    {
        $jwtToken = $request['jwt_token'] ?? $this->config->apiKey;
        unset($request['jwt_token']);
        
        return $this->call('APIKeyService.RevokeAPIKey', $request, $jwtToken);
    }

    /**
     * Список API ключей
     *
     * @param array $request Параметры запроса
     * @return array Ответ со списком API ключей
     * @throws AuthException
     */
    public function listAPIKeys(array $request): array
    {
        $jwtToken = $request['jwt_token'] ?? $this->config->apiKey;
        unset($request['jwt_token']);
        
        return $this->call('APIKeyService.ListAPIKeys', $request, $jwtToken);
    }
}
