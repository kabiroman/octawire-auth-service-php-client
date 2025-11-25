<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Kabiroman\Octawire\AuthService\Client\Config;
use Kabiroman\Octawire\AuthService\Client\AuthClient;
use Kabiroman\Octawire\AuthService\Client\Exception\AuthException;

// Создаем конфигурацию для TCP (JATP)
$config = new Config([
    'transport' => 'tcp',
    'address' => 'localhost:50052', // TCP port
    'project_id' => 'default-project-id',
]);

// Создаем клиент
try {
    $client = new AuthClient($config);
} catch (AuthException $e) {
    die("Failed to create client: " . $e->getMessage() . "\n");
}

// Пример 1: Выдача токена
echo "=== IssueToken ===\n";
try {
    $response = $client->issueToken([
        'user_id' => 'user-123',
        'claims' => ['role' => 'admin'],
        'access_token_ttl' => 3600,  // 1 час
        'refresh_token_ttl' => 86400, // 24 часа
    ]);

    echo "Access Token: " . substr($response['access_token'] ?? '', 0, 50) . "...\n";
    echo "Refresh Token: " . substr($response['refresh_token'] ?? '', 0, 50) . "...\n";
    if (isset($response['access_token_expires_at'])) {
        echo "Access Token Expires At: " . date('Y-m-d H:i:s', $response['access_token_expires_at']) . "\n";
    }
} catch (AuthException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Пример 2: Валидация токена
echo "\n=== ValidateToken ===\n";
if (isset($response['access_token'])) {
    try {
        $validateResponse = $client->validateToken([
            'token' => $response['access_token'],
            'check_blacklist' => true,
        ]);

        if ($validateResponse['valid'] ?? false) {
            echo "Token is valid\n";
            if (isset($validateResponse['claims'])) {
                $claims = $validateResponse['claims'];
                echo "User ID: " . ($claims['user_id'] ?? 'N/A') . "\n";
                if (isset($claims['issued_at'])) {
                    echo "Issued At: " . date('Y-m-d H:i:s', $claims['issued_at']) . "\n";
                }
                if (isset($claims['expires_at'])) {
                    echo "Expires At: " . date('Y-m-d H:i:s', $claims['expires_at']) . "\n";
                }
            }
        } else {
            echo "Token is invalid: " . ($validateResponse['error'] ?? 'Unknown error') . "\n";
        }
    } catch (AuthException $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

// Пример 3: Обновление токена
echo "\n=== RefreshToken ===\n";
if (isset($response['refresh_token'])) {
    try {
        $refreshResponse = $client->refreshToken([
            'refresh_token' => $response['refresh_token'],
        ]);

        echo "New Access Token: " . substr($refreshResponse['access_token'] ?? '', 0, 50) . "...\n";
        if (isset($refreshResponse['refresh_token']) && !empty($refreshResponse['refresh_token'])) {
            echo "New Refresh Token: " . substr($refreshResponse['refresh_token'], 0, 50) . "...\n";
        }
    } catch (AuthException $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

// Пример 4: Health Check
echo "\n=== HealthCheck ===\n";
try {
    $healthResponse = $client->healthCheck();
    echo "Service is healthy: " . ($healthResponse['healthy'] ?? false ? 'true' : 'false') . "\n";
    echo "Version: " . ($healthResponse['version'] ?? 'N/A') . "\n";
    if (isset($healthResponse['uptime'])) {
        echo "Uptime: " . $healthResponse['uptime'] . " seconds\n";
    }
} catch (AuthException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Закрываем соединение
$client->close();

echo "\n✓ All examples completed!\n";

