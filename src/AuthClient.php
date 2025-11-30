<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client;

use Kabiroman\Octawire\AuthService\Client\Exception\AuthException;
use Kabiroman\Octawire\AuthService\Client\Exception\ConnectionException;
use Kabiroman\Octawire\AuthService\Client\Transport\JATPClient;
use Kabiroman\Octawire\AuthService\Client\Request\JWT as JWTRequest;
use Kabiroman\Octawire\AuthService\Client\Response\JWT as JWTResponse;
use Kabiroman\Octawire\AuthService\Client\Request\APIKey as APIKeyRequest;
use Kabiroman\Octawire\AuthService\Client\Response\APIKey as APIKeyResponse;

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
     * Выполнение JATP запроса с retry логикой (внутренний метод)
     *
     * @param string $method Метод (например, "JWTService.IssueToken")
     * @param array|\stdClass $payload Полезная нагрузка запроса
     * @param string|null $jwtToken JWT токен для аутентификации
     * @param string|null $serviceName Имя сервиса для межсервисной аутентификации
     * @param string|null $serviceSecret Секрет сервиса для межсервисной аутентификации
     * @return array Ответ от сервера
     * @throws AuthException
     */
    private function callRaw(
        string $method,
        array|\stdClass $payload,
        ?string $jwtToken = null,
        ?string $serviceName = null,
        ?string $serviceSecret = null
    ): array {
        // Методы, которые принимают project_id в payload (согласно JATP_METHODS_1.0.json)
        // project_id теперь всегда обязателен в payload для этих методов (v0.9.3+)
        $methodsWithProjectId = [
            'JWTService.IssueToken',
            'JWTService.IssueServiceToken',
            'JWTService.ValidateToken',
            'JWTService.RefreshToken',
            'JWTService.ParseToken',
            'JWTService.ExtractClaims',
            'JWTService.RevokeToken',
            'JWTService.GetPublicKey',
            'APIKeyService.CreateAPIKey',
            'APIKeyService.RevokeAPIKey',
            'APIKeyService.ListAPIKeys',
        ];

        // project_id теперь всегда должен быть в payload от Request классов
        // Удалена логика автоматического добавления из конфига

        return $this->retryHandler->execute(function () use ($method, $payload, $jwtToken, $serviceName, $serviceSecret) {
            try {
                $response = $this->jatpClient->call($method, $payload, $jwtToken, $serviceName, $serviceSecret);
                return $response;
            } catch (\Exception $e) {
                throw ErrorHandler::wrapError($e);
            }
        });
    }

    // ============================================================================
    // JWT Service методы (типизированные согласно JATP_METHODS_1.0.json)
    // ============================================================================

    /**
     * Выдача нового JWT токена (access + refresh)
     * project_id обязателен в Request (v0.9.3+)
     */
    public function issueToken(JWTRequest\IssueTokenRequest $request): JWTResponse\IssueTokenResponse
    {
        $payload = $request->toArray();
        
        $response = $this->callRaw('JWTService.IssueToken', $payload);
        return JWTResponse\IssueTokenResponse::fromArray($response);
    }

    /**
     * Выдача межсервисного JWT токена
     * project_id обязателен в Request (v0.9.3+)
     * Service authentication опциональна (v1.0+)
     *
     * @param JWTRequest\IssueServiceTokenRequest $request Запрос на выдачу токена
     * @param string|null $serviceSecret Секрет сервиса (опционально, если не указан - вызывается без service auth)
     * @return JWTResponse\IssueTokenResponse
     */
    public function issueServiceToken(
        JWTRequest\IssueServiceTokenRequest $request,
        ?string $serviceSecret = null
    ): JWTResponse\IssueTokenResponse {
        // Валидация: sourceService обязателен
        if (empty($request->sourceService)) {
            throw new AuthException('sourceService is required for IssueServiceToken', 400);
        }

        // Используем serviceSecret из параметра или из конфигурации
        // Если не указан - метод будет вызван без service auth (для localhost/optional scenarios v1.0+)
        $serviceSecret = $serviceSecret ?? $this->config->serviceSecret;
        
        $payload = $request->toArray();
        
        $response = $this->callRaw(
            'JWTService.IssueServiceToken',
            $payload,
            null, // jwtToken
            $request->sourceService, // serviceName
            $serviceSecret // может быть null - будет опциональная auth (v1.0+)
        );
        
        return JWTResponse\IssueTokenResponse::fromArray($response);
    }

    /**
     * Валидация токена
     * project_id обязателен в Request (v0.9.3+)
     * Authentication опциональна (v1.0+): может использовать service auth или работать как публичный метод
     *
     * @param JWTRequest\ValidateTokenRequest $request Запрос на валидацию токена
     * @param string|null $serviceName Имя сервиса для service auth (опционально)
     * @param string|null $serviceSecret Секрет сервиса для service auth (опционально)
     * @return JWTResponse\ValidateTokenResponse
     */
    public function validateToken(
        JWTRequest\ValidateTokenRequest $request,
        ?string $serviceName = null,
        ?string $serviceSecret = null
    ): JWTResponse\ValidateTokenResponse {
        // Если service auth не указана - используем из конфига или вызываем как публичный метод
        $serviceSecret = $serviceSecret ?? $this->config->serviceSecret;
        
        // Если serviceSecret есть, но serviceName не указан - не используем service auth (будет публичный метод)
        // Если оба указаны - используем service auth
        
        $payload = $request->toArray();
        
        $response = $this->callRaw(
            'JWTService.ValidateToken',
            $payload,
            null, // jwtToken - не используется для этого метода (v1.0+)
            $serviceName,
            $serviceSecret
        );
        
        return JWTResponse\ValidateTokenResponse::fromArray($response);
    }

    /**
     * Обновление токена
     * project_id обязателен в Request (v0.9.3+)
     */
    public function refreshToken(JWTRequest\RefreshTokenRequest $request): JWTResponse\RefreshTokenResponse
    {
        $payload = $request->toArray();
        
        $response = $this->callRaw('JWTService.RefreshToken', $payload);
        return JWTResponse\RefreshTokenResponse::fromArray($response);
    }

    /**
     * Отзыв токена
     * project_id обязателен в Request (v0.9.3+)
     */
    public function revokeToken(
        JWTRequest\RevokeTokenRequest $request,
        ?string $jwtToken = null
    ): JWTResponse\RevokeTokenResponse {
        $jwtToken ??= $this->config->apiKey;
        $payload = $request->toArray();
        
        $response = $this->callRaw('JWTService.RevokeToken', $payload, $jwtToken);
        return JWTResponse\RevokeTokenResponse::fromArray($response);
    }

    /**
     * Парсинг токена без валидации
     * project_id обязателен в Request (v0.9.3+)
     * Authentication опциональна (v1.0+): может использовать service auth или работать как публичный метод
     *
     * @param JWTRequest\ParseTokenRequest $request Запрос на парсинг токена
     * @param string|null $serviceName Имя сервиса для service auth (опционально)
     * @param string|null $serviceSecret Секрет сервиса для service auth (опционально)
     * @return JWTResponse\ParseTokenResponse
     */
    public function parseToken(
        JWTRequest\ParseTokenRequest $request,
        ?string $serviceName = null,
        ?string $serviceSecret = null
    ): JWTResponse\ParseTokenResponse {
        // Если service auth не указана - используем из конфига или вызываем как публичный метод
        $serviceSecret = $serviceSecret ?? $this->config->serviceSecret;
        
        $payload = $request->toArray();
        
        $response = $this->callRaw(
            'JWTService.ParseToken',
            $payload,
            null, // jwtToken - не используется для этого метода (v1.0+)
            $serviceName,
            $serviceSecret
        );
        
        return JWTResponse\ParseTokenResponse::fromArray($response);
    }

    /**
     * Извлечение claims из токена
     * project_id обязателен в Request (v0.9.3+)
     * Authentication опциональна (v1.0+): может использовать service auth или работать как публичный метод
     *
     * @param JWTRequest\ExtractClaimsRequest $request Запрос на извлечение claims
     * @param string|null $serviceName Имя сервиса для service auth (опционально)
     * @param string|null $serviceSecret Секрет сервиса для service auth (опционально)
     * @return JWTResponse\ExtractClaimsResponse
     */
    public function extractClaims(
        JWTRequest\ExtractClaimsRequest $request,
        ?string $serviceName = null,
        ?string $serviceSecret = null
    ): JWTResponse\ExtractClaimsResponse {
        // Если service auth не указана - используем из конфига или вызываем как публичный метод
        $serviceSecret = $serviceSecret ?? $this->config->serviceSecret;
        
        $payload = $request->toArray();
        
        $response = $this->callRaw(
            'JWTService.ExtractClaims',
            $payload,
            null, // jwtToken - не используется для этого метода (v1.0+)
            $serviceName,
            $serviceSecret
        );
        
        return JWTResponse\ExtractClaimsResponse::fromArray($response);
    }

    /**
     * Пакетная валидация токенов
     * НЕ принимает project_id - определяется автоматически из токенов
     * Authentication опциональна (v1.0+): может использовать service auth или работать как публичный метод
     *
     * @param JWTRequest\ValidateBatchRequest $request Запрос на пакетную валидацию
     * @param string|null $serviceName Имя сервиса для service auth (опционально)
     * @param string|null $serviceSecret Секрет сервиса для service auth (опционально)
     * @return JWTResponse\ValidateBatchResponse
     */
    public function validateBatch(
        JWTRequest\ValidateBatchRequest $request,
        ?string $serviceName = null,
        ?string $serviceSecret = null
    ): JWTResponse\ValidateBatchResponse {
        // Если service auth не указана - используем из конфига или вызываем как публичный метод
        $serviceSecret = $serviceSecret ?? $this->config->serviceSecret;
        
        $payload = $request->toArray();
        
        $response = $this->callRaw(
            'JWTService.ValidateBatch',
            $payload,
            null, // jwtToken - не используется для этого метода (v1.0+)
            $serviceName,
            $serviceSecret
        );
        
        return JWTResponse\ValidateBatchResponse::fromArray($response);
    }

    /**
     * Получение публичного ключа (с кэшированием)
     */
    public function getPublicKey(JWTRequest\GetPublicKeyRequest $request): JWTResponse\GetPublicKeyResponse
    {
        // Запрашиваем у сервера
        $payload = $request->toArray();
        $response = $this->callRaw('JWTService.GetPublicKey', $payload);
        $result = JWTResponse\GetPublicKeyResponse::fromArray($response);

        // Кэшируем все активные ключи для использования в других местах
        if ($this->keyCache !== null && !empty($result->activeKeys)) {
            $this->keyCache->setAllActive($request->projectId, $result->activeKeys, $result->cacheUntil);
        }

        return $result;
    }

    /**
     * Проверка здоровья сервиса
     * Публичный метод, не требует аутентификации
     */
    public function healthCheck(JWTRequest\HealthCheckRequest $request = new JWTRequest\HealthCheckRequest()): JWTResponse\HealthCheckResponse
    {
        $payload = $request->toArray();
        $response = $this->callRaw('JWTService.HealthCheck', $payload);
        return JWTResponse\HealthCheckResponse::fromArray($response);
    }

    // ============================================================================
    // API Key Service методы (типизированные согласно JATP_METHODS_1.0.json)
    // ============================================================================

    /**
     * Создание нового API ключа
     */
    public function createAPIKey(
        APIKeyRequest\CreateAPIKeyRequest $request,
        ?string $jwtToken = null
    ): APIKeyResponse\CreateAPIKeyResponse {
        $jwtToken ??= $this->config->apiKey;
        $payload = $request->toArray();
        
        $response = $this->callRaw('APIKeyService.CreateAPIKey', $payload, $jwtToken);
        return APIKeyResponse\CreateAPIKeyResponse::fromArray($response);
    }

    /**
     * Валидация API ключа
     * НЕ принимает project_id - определяется автоматически из ключа
     */
    public function validateAPIKey(
        APIKeyRequest\ValidateAPIKeyRequest $request,
        ?string $jwtToken = null
    ): APIKeyResponse\ValidateAPIKeyResponse {
        $jwtToken ??= $this->config->apiKey;
        $payload = $request->toArray();
        
        $response = $this->callRaw('APIKeyService.ValidateAPIKey', $payload, $jwtToken);
        return APIKeyResponse\ValidateAPIKeyResponse::fromArray($response);
    }

    /**
     * Отзыв API ключа
     */
    public function revokeAPIKey(
        APIKeyRequest\RevokeAPIKeyRequest $request,
        ?string $jwtToken = null
    ): APIKeyResponse\RevokeAPIKeyResponse {
        $jwtToken ??= $this->config->apiKey;
        $payload = $request->toArray();
        
        $response = $this->callRaw('APIKeyService.RevokeAPIKey', $payload, $jwtToken);
        return APIKeyResponse\RevokeAPIKeyResponse::fromArray($response);
    }

    /**
     * Список API ключей
     */
    public function listAPIKeys(
        APIKeyRequest\ListAPIKeysRequest $request,
        ?string $jwtToken = null
    ): APIKeyResponse\ListAPIKeysResponse {
        $jwtToken ??= $this->config->apiKey;
        $payload = $request->toArray();
        
        $response = $this->callRaw('APIKeyService.ListAPIKeys', $payload, $jwtToken);
        return APIKeyResponse\ListAPIKeysResponse::fromArray($response);
    }
}
