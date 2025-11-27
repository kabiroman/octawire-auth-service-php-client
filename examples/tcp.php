<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Kabiroman\Octawire\AuthService\Client\Config;
use Kabiroman\Octawire\AuthService\Client\AuthClient;
use Kabiroman\Octawire\AuthService\Client\Exception\AuthException;
use Kabiroman\Octawire\AuthService\Client\Request\JWT\IssueTokenRequest;
use Kabiroman\Octawire\AuthService\Client\Request\JWT\ValidateTokenRequest;
use Kabiroman\Octawire\AuthService\Client\Request\JWT\RefreshTokenRequest;
use Kabiroman\Octawire\AuthService\Client\Request\JWT\IssueServiceTokenRequest;

echo "=== PHP JATP Client Example ===\n\n";

// Создаем конфигурацию для TCP (JATP)
$config = new Config([
    'transport' => 'tcp',
    'tcp' => [
        'host' => 'localhost',
        'port' => 50052,
        'tls' => [
            'enabled' => false, // true для production
            // 'ca_file' => '/path/to/ca.crt',
            // 'cert_file' => '/path/to/client.crt', // для mTLS
            // 'key_file' => '/path/to/client.key', // для mTLS
            // 'server_name' => 'auth-service.example.com',
        ],
        'persistent' => true, // Переиспользование соединений
    ],
    'project_id' => 'default-project-id',
    'timeout' => [
        'connect' => 10.0,
        'request' => 30.0,
    ],
    'retry' => [
        'max_attempts' => 3,
        'initial_backoff' => 0.1,
        'max_backoff' => 5.0,
    ],
]);

// Создаем клиент
try {
    $client = new AuthClient($config);
    echo "✓ Client created successfully\n\n";
} catch (AuthException $e) {
    die("Failed to create client: " . $e->getMessage() . "\n");
}

// Пример 1: Health Check (публичный метод)
echo "=== HealthCheck ===\n";
try {
    $response = $client->healthCheck();
    echo "Healthy: " . ($response['healthy'] ?? false ? 'true' : 'false') . "\n";
    echo "Version: " . ($response['version'] ?? 'N/A') . "\n";
    if (isset($response['uptime'])) {
        echo "Uptime: " . $response['uptime'] . " seconds\n";
    }
    echo "✓ Health check successful\n\n";
} catch (AuthException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
}

// Пример 2: Выдача токена
echo "=== IssueToken ===\n";
try {
    $request = new IssueTokenRequest(
        userId: 'user-123',
        projectId: 'default-project-id', // Обязательное поле (v0.9.3+)
        claims: ['role' => 'admin', 'email' => 'user@example.com'],
        accessTokenTtl: 3600,  // 1 час
        refreshTokenTtl: 86400, // 24 часа
    );
    
    $response = $client->issueToken($request);

    $accessToken = $response->accessToken;
    $refreshToken = $response->refreshToken;
    
    echo "Access Token: " . substr($accessToken, 0, 50) . "...\n";
    echo "Refresh Token: " . substr($refreshToken, 0, 50) . "...\n";
    echo "Access Token Expires At: " . date('Y-m-d H:i:s', $response->accessTokenExpiresAt) . "\n";
    echo "✓ Token issued successfully\n\n";
} catch (AuthException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
    $accessToken = null;
    $refreshToken = null;
}

// Пример 3: Валидация токена
if (isset($accessToken) && !empty($accessToken)) {
    echo "=== ValidateToken ===\n";
    try {
        $validateRequest = new ValidateTokenRequest(
            token: $accessToken,
            projectId: 'default-project-id', // Обязательное поле (v0.9.3+)
            checkBlacklist: true,
        );
        
        $validateResponse = $client->validateToken($validateRequest);

        if ($validateResponse->valid) {
            echo "✓ Token is valid\n";
            if ($validateResponse->claims !== null) {
                $claims = $validateResponse->claims;
                echo "User ID: " . ($claims->userId ?? 'N/A') . "\n";
                if (isset($claims->issuedAt)) {
                    echo "Issued At: " . date('Y-m-d H:i:s', $claims->issuedAt) . "\n";
                }
                if (isset($claims->expiresAt)) {
                    echo "Expires At: " . date('Y-m-d H:i:s', $claims->expiresAt) . "\n";
                }
            }
        } else {
            echo "✗ Token is invalid: " . ($validateResponse->error ?? 'Unknown error') . "\n";
        }
        echo "\n";
    } catch (AuthException $e) {
        echo "✗ Error: " . $e->getMessage() . "\n\n";
    }
}

// Пример 4: Обновление токена
if (isset($refreshToken) && !empty($refreshToken)) {
    echo "=== RefreshToken ===\n";
    try {
        $refreshRequest = new RefreshTokenRequest(
            refreshToken: $refreshToken,
            projectId: 'default-project-id', // Обязательное поле (v0.9.3+)
        );
        
        $refreshResponse = $client->refreshToken($refreshRequest);

        echo "New Access Token: " . substr($refreshResponse->accessToken, 0, 50) . "...\n";
        if ($refreshResponse->refreshToken !== null && !empty($refreshResponse->refreshToken)) {
            echo "New Refresh Token: " . substr($refreshResponse->refreshToken, 0, 50) . "...\n";
        }
        echo "✓ Token refreshed successfully\n\n";
    } catch (AuthException $e) {
        echo "✗ Error: " . $e->getMessage() . "\n\n";
    }
}

// Пример 5: Получение публичного ключа (с кэшированием)
echo "=== GetPublicKey ===\n";
try {
    $keyResponse = $client->getPublicKey([
        'project_id' => 'default-project-id',
    ]);

    echo "Key ID: " . ($keyResponse['key_id'] ?? 'N/A') . "\n";
    echo "Algorithm: " . ($keyResponse['algorithm'] ?? 'N/A') . "\n";
    if (isset($keyResponse['cache_until'])) {
        echo "Cache Until: " . date('Y-m-d H:i:s', $keyResponse['cache_until']) . "\n";
    }
    echo "✓ Public key retrieved successfully\n\n";
} catch (AuthException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
}

// Пример 6: Межсервисный токен
echo "=== IssueServiceToken ===\n";
try {
    $request = new IssueServiceTokenRequest(
        sourceService: 'identity-service',
        projectId: 'default-project-id', // Обязательное поле (v0.9.3+)
        targetService: 'gateway-service',
        userId: 'service-user',
        claims: ['service' => 'identity-service'],
        ttl: 3600,
    );
    
    // Service secret передается отдельным параметром (не в payload)
    $serviceSecret = 'identity-service-secret-abc123def456';
    $serviceResponse = $client->issueServiceToken($request, $serviceSecret);

    echo "Service Token: " . substr($serviceResponse->accessToken, 0, 50) . "...\n";
    echo "Expires At: " . date('Y-m-d H:i:s', $serviceResponse->accessTokenExpiresAt) . "\n";
    echo "✓ Service token issued successfully\n\n";
} catch (AuthException $e) {
    // Обработка AUTH_FAILED ошибки для service authentication
    if ($e->getErrorCode() === 'AUTH_FAILED') {
        echo "✗ Authentication failed: Invalid service credentials\n";
        echo "  Error: " . $e->getMessage() . "\n\n";
    } else {
        echo "✗ Error: " . $e->getMessage() . "\n\n";
    }
}

// Закрываем соединение
$client->close();

echo "✓ All examples completed!\n";

