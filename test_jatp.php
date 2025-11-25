<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Kabiroman\Octawire\AuthService\Client\Config;
use Kabiroman\Octawire\AuthService\Client\AuthClient;
use Kabiroman\Octawire\AuthService\Client\Exception\AuthException;

echo "=== PHP JATP Client Test ===\n\n";

// Конфигурация для подключения к локальному сервису
$config = new Config([
    'transport' => 'tcp',
    'tcp' => [
        'host' => 'localhost',
        'port' => 50052, // TCP порт
        'tls' => [
            'enabled' => false, // Без TLS для локального теста
        ],
        'persistent' => true,
    ],
    'project_id' => null, // Будет использоваться дефолтный
    'timeout' => [
        'connect' => 5.0,
        'request' => 10.0,
    ],
]);

echo "Connecting to auth-service at localhost:50052...\n";

try {
    $client = new AuthClient($config);
    echo "✓ Client created successfully\n\n";
} catch (\Exception $e) {
    die("✗ Failed to create client: " . $e->getMessage() . "\n");
}

// Тест 1: Health Check
echo "--- Test 1: HealthCheck ---\n";
try {
    $response = $client->healthCheck();
    echo "✓ Health check successful\n";
    echo "  Healthy: " . ($response['healthy'] ?? 'N/A') . "\n";
    echo "  Version: " . ($response['version'] ?? 'N/A') . "\n";
    if (isset($response['uptime'])) {
        echo "  Uptime: " . $response['uptime'] . " seconds\n";
    }
} catch (AuthException $e) {
    echo "✗ Health check failed: " . $e->getMessage() . "\n";
    echo "  Make sure auth-service is running with TCP gateway enabled\n";
    $client->close();
    exit(1);
}
echo "\n";

// Тест 2: IssueToken
echo "--- Test 2: IssueToken ---\n";
try {
    $response = $client->issueToken([
        'user_id' => 'test-user-123',
        'claims' => [
            'role' => 'admin',
            'email' => 'test@example.com',
        ],
        'access_token_ttl' => 3600,
        'refresh_token_ttl' => 86400,
    ]);

    $accessToken = $response['accessToken'] ?? $response['access_token'] ?? null;
    $refreshToken = $response['refreshToken'] ?? $response['refresh_token'] ?? null;

    if ($accessToken && $refreshToken) {
        echo "✓ Token issued successfully\n";
        echo "  Access Token: " . substr($accessToken, 0, 50) . "...\n";
        echo "  Refresh Token: " . substr($refreshToken, 0, 50) . "...\n";
    } else {
        echo "✗ Token issue failed: missing tokens in response\n";
        print_r($response);
    }
} catch (AuthException $e) {
    echo "✗ IssueToken failed: " . $e->getMessage() . "\n";
    $accessToken = null;
    $refreshToken = null;
}
echo "\n";

// Тест 3: ValidateToken
if (isset($accessToken) && !empty($accessToken)) {
    echo "--- Test 3: ValidateToken ---\n";
    try {
        $response = $client->validateToken([
            'token' => $accessToken,
            'check_blacklist' => false, // Упрощенный тест
        ]);

        if ($response['valid'] ?? false) {
            echo "✓ Token validation successful\n";
            if (isset($response['claims'])) {
                $claims = $response['claims'];
                echo "  User ID: " . ($claims['user_id'] ?? 'N/A') . "\n";
                echo "  Issuer: " . ($claims['issuer'] ?? 'N/A') . "\n";
                if (isset($claims['expires_at'])) {
                    echo "  Expires At: " . date('Y-m-d H:i:s', $claims['expires_at']) . "\n";
                }
            }
        } else {
            echo "✗ Token validation failed: " . ($response['error'] ?? 'Unknown error') . "\n";
        }
    } catch (AuthException $e) {
        echo "✗ ValidateToken failed: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

// Тест 4: GetPublicKey
echo "--- Test 4: GetPublicKey ---\n";
try {
    $response = $client->getPublicKey([
        // project_id будет использован из конфига или дефолтный
    ]);

    $publicKey = $response['publicKeyPem'] ?? $response['public_key_pem'] ?? null;
    if ($publicKey) {
        echo "✓ Public key retrieved successfully\n";
        echo "  Key ID: " . ($response['keyId'] ?? $response['key_id'] ?? 'N/A') . "\n";
        echo "  Algorithm: " . ($response['algorithm'] ?? 'N/A') . "\n";
        $keyPreview = substr($publicKey, 0, 50);
        echo "  Public Key: " . $keyPreview . "...\n";
    } else {
        echo "✗ GetPublicKey failed: missing key in response\n";
        print_r($response);
    }
} catch (AuthException $e) {
    echo "✗ GetPublicKey failed: " . $e->getMessage() . "\n";
}
echo "\n";

// Тест 5: RefreshToken
if (isset($refreshToken) && !empty($refreshToken)) {
    echo "--- Test 5: RefreshToken ---\n";
    try {
        $response = $client->refreshToken([
            'refresh_token' => $refreshToken,
        ]);

        $newAccessToken = $response['accessToken'] ?? $response['access_token'] ?? null;
        if ($newAccessToken) {
            echo "✓ Token refresh successful\n";
            echo "  New Access Token: " . substr($newAccessToken, 0, 50) . "...\n";
        } else {
            echo "✗ RefreshToken failed: missing token in response\n";
            print_r($response);
        }
    } catch (AuthException $e) {
        echo "✗ RefreshToken failed: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

$client->close();

echo "=== All tests completed! ===\n";

