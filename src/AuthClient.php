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
        $methodsWithProjectId = [
            'JWTService.IssueToken',
            'JWTService.IssueServiceToken',
            'JWTService.GetPublicKey',
            'APIKeyService.CreateAPIKey',
            'APIKeyService.RevokeAPIKey',
            'APIKeyService.ListAPIKeys',
        ];

        // Добавляем project_id только для методов, которые его принимают
        if (in_array($method, $methodsWithProjectId, true)) {
            if (is_array($payload) && !isset($payload['project_id']) && $this->config->projectId !== null) {
                $payload['project_id'] = $this->config->projectId;
            }
        }

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
     */
    public function issueToken(JWTRequest\IssueTokenRequest $request): JWTResponse\IssueTokenResponse
    {
        $payload = $request->toArray();
        
        // Добавляем project_id из конфига если не указан
        if (!isset($payload['project_id']) && $this->config->projectId !== null) {
            $payload['project_id'] = $this->config->projectId;
        }
        
        $response = $this->callRaw('JWTService.IssueToken', $payload);
        return JWTResponse\IssueTokenResponse::fromArray($response);
    }

    /**
     * Выдача межсервисного JWT токена
     *
     * @param JWTRequest\IssueServiceTokenRequest $request Запрос на выдачу токена
     * @param string|null $serviceSecret Секрет сервиса (если не указан, используется из конфигурации)
     * @return JWTResponse\IssueTokenResponse
     * @throws AuthException Если serviceSecret не указан и отсутствует в конфигурации
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
        $serviceSecret = $serviceSecret ?? $this->config->serviceSecret;
        
        // Валидация: serviceSecret обязателен
        if (empty($serviceSecret)) {
            throw new AuthException(
                'serviceSecret is required for IssueServiceToken. Provide it as parameter or in config.',
                400
            );
        }

        $payload = $request->toArray();
        
        // Добавляем project_id из конфига если не указан
        if (!isset($payload['project_id']) && $this->config->projectId !== null) {
            $payload['project_id'] = $this->config->projectId;
        }
        
        $response = $this->callRaw(
            'JWTService.IssueServiceToken',
            $payload,
            null,
            $request->sourceService,
            $serviceSecret
        );
        
        return JWTResponse\IssueTokenResponse::fromArray($response);
    }

    /**
     * Валидация токена
     * НЕ принимает project_id - определяется автоматически из токена
     */
    public function validateToken(
        JWTRequest\ValidateTokenRequest $request,
        ?string $jwtToken = null
    ): JWTResponse\ValidateTokenResponse {
        $jwtToken ??= $this->config->apiKey;
        $payload = $request->toArray();
        
        $response = $this->callRaw('JWTService.ValidateToken', $payload, $jwtToken);
        return JWTResponse\ValidateTokenResponse::fromArray($response);
    }

    /**
     * Обновление токена
     * НЕ принимает project_id - определяется автоматически из refresh token
     */
    public function refreshToken(JWTRequest\RefreshTokenRequest $request): JWTResponse\RefreshTokenResponse
    {
        $payload = $request->toArray();
        
        $response = $this->callRaw('JWTService.RefreshToken', $payload);
        return JWTResponse\RefreshTokenResponse::fromArray($response);
    }

    /**
     * Отзыв токена
     * НЕ принимает project_id - определяется автоматически из токена
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
     * НЕ принимает project_id - определяется автоматически из токена
     */
    public function parseToken(
        JWTRequest\ParseTokenRequest $request,
        ?string $jwtToken = null
    ): JWTResponse\ParseTokenResponse {
        $jwtToken ??= $this->config->apiKey;
        $payload = $request->toArray();
        
        $response = $this->callRaw('JWTService.ParseToken', $payload, $jwtToken);
        return JWTResponse\ParseTokenResponse::fromArray($response);
    }

    /**
     * Извлечение claims из токена
     * НЕ принимает project_id - определяется автоматически из токена
     */
    public function extractClaims(
        JWTRequest\ExtractClaimsRequest $request,
        ?string $jwtToken = null
    ): JWTResponse\ExtractClaimsResponse {
        $jwtToken ??= $this->config->apiKey;
        $payload = $request->toArray();
        
        $response = $this->callRaw('JWTService.ExtractClaims', $payload, $jwtToken);
        return JWTResponse\ExtractClaimsResponse::fromArray($response);
    }

    /**
     * Пакетная валидация токенов
     * НЕ принимает project_id - определяется автоматически из токенов
     */
    public function validateBatch(
        JWTRequest\ValidateBatchRequest $request,
        ?string $jwtToken = null
    ): JWTResponse\ValidateBatchResponse {
        $jwtToken ??= $this->config->apiKey;
        $payload = $request->toArray();
        
        $response = $this->callRaw('JWTService.ValidateBatch', $payload, $jwtToken);
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
