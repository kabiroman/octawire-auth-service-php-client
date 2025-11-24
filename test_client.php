<?php

declare(strict_types=1);

/**
 * Тестовый скрипт для PHP TCP/JSON клиента Auth Service.
 * Проверяет базовые компоненты (Config, RetryHandler, KeyCache) и доступность сервисов.
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Config.php';

use Kabiroman\Octawire\AuthService\Client\Config;
use Kabiroman\Octawire\AuthService\Client\KeyCache;
use Kabiroman\Octawire\AuthService\Client\Model\PublicKeyInfo;
use Kabiroman\Octawire\AuthService\Client\RetryHandler;

echo "=== PHP TCP Client Smoke Test ===\n\n";

// 1. Проверяем расширения PHP, необходимые для TCP клиента
echo "1. Checking PHP extensions...\n";
$socketsLoaded = extension_loaded('sockets');
$jsonLoaded = extension_loaded('json');
echo "   - sockets: " . ($socketsLoaded ? "✓ Loaded" : "✗ Not loaded") . "\n";
echo "   - json: " . ($jsonLoaded ? "✓ Loaded" : "✗ Not loaded") . "\n";
if (!$socketsLoaded || !$jsonLoaded) {
    echo "⚠️  Required extensions are missing. Install them and rerun tests.\n";
    exit(1);
}

// 2. KeyCache
echo "\n2. Testing KeyCache...\n";
try {
    $cache = new KeyCache();

    $projectId = 'test-project';
    $keyId = 'key-1';
    $keyInfo = new PublicKeyInfo(
        keyId: $keyId,
        publicKeyPem: 'test-public-key-pem',
        isPrimary: true,
        expiresAt: time() + 3600
    );

    $cache->set($projectId, $keyInfo, time() + 1800);
    $cached = $cache->get($projectId, $keyId);
    echo $cached && $cached->keyId === $keyId
        ? "   ✓ KeyCache: get/set works\n"
        : "   ✗ KeyCache: get/set failed\n";

    $active = $cache->getAllActive($projectId);
    echo count($active) === 1
        ? "   ✓ KeyCache: getAllActive works\n"
        : "   ✗ KeyCache: getAllActive failed\n";

    $cache->invalidate($projectId);
    echo $cache->get($projectId, $keyId) === null
        ? "   ✓ KeyCache: invalidate works\n"
        : "   ✗ KeyCache: invalidate failed\n";
} catch (\Throwable $e) {
    echo "   ✗ KeyCache test failed: {$e->getMessage()}\n";
}

// 3. RetryHandler
echo "\n3. Testing RetryHandler...\n";
try {
    $handler = new RetryHandler();

    $result = $handler->execute(fn () => 'success');
    echo $result === 'success'
        ? "   ✓ RetryHandler: successful execution works\n"
        : "   ✗ RetryHandler: successful execution failed\n";

    $nonRetryableThrown = false;
    try {
        $handler->execute(fn () => throw new \InvalidArgumentException('Non-retryable'));
    } catch (\InvalidArgumentException) {
        $nonRetryableThrown = true;
    }
    echo $nonRetryableThrown
        ? "   ✓ RetryHandler: non-retryable error handling works\n"
        : "   ✗ RetryHandler: non-retryable error handling failed\n";
} catch (\Throwable $e) {
    echo "   ✗ RetryHandler test failed: {$e->getMessage()}\n";
}

// 4. Config + TCP параметры
echo "\n4. Testing Config (TCP)...\n";
try {
    $config = new Config([
        'address' => sprintf('%s:%s', getenv('AUTH_TCP_HOST') ?: 'localhost', getenv('AUTH_TCP_PORT') ?: '9770'),
        'project_id' => 'test-project',
        'api_key' => 'test-api-key',
    ]);

    $addressOk = str_contains($config->address, ':');
    $projectOk = $config->projectId === 'test-project';
    $apiKeyOk = $config->apiKey === 'test-api-key';

    echo ($addressOk && $projectOk && $apiKeyOk)
        ? "   ✓ Config: TCP params resolved\n"
        : "   ✗ Config: TCP params invalid\n";
} catch (\Throwable $e) {
    echo "   ✗ Config test failed: {$e->getMessage()}\n";
}

// 5. Health check HTTP API (gRPC остаётся для Go клиентов)
echo "\n5. Checking Auth Service health endpoint...\n";
$healthUrl = 'http://' . (($host = getenv('AUTH_HEALTH_HOST')) ?: 'localhost') . ':' . (($port = getenv('AUTH_HEALTH_PORT')) ?: '9765') . '/health';
$ch = curl_init($healthUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 2,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200 && $response) {
    $health = json_decode($response, true);
    if ($health && ($health['healthy'] ?? false)) {
        echo "   ✓ Auth Service is healthy\n";
        echo "   - Version: " . ($health['version'] ?? 'N/A') . "\n";
        echo "   - Uptime: " . ($health['uptime'] ?? 'N/A') . " seconds\n";
    } else {
        echo "   ✗ Auth Service health payload invalid\n";
    }
} else {
    echo "   ✗ Auth Service is not reachable at $healthUrl\n";
}

echo "\n=== Test Summary ===\n";
echo "TCP client prerequisites validated (sockets/json extensions, config, cache, retry).\n";
echo "Full TCP transport tests will be added после реализации AuthClient поверх JSON/TCP.\n";
echo "\n✓ Test completed!\n";