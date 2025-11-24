<?php

declare(strict_types=1);

/**
 * Тестовый скрипт для PHP клиента Auth Service
 * Аналогичен тестированию Go клиента
 */

require_once __DIR__ . '/vendor/autoload.php';
// Загружаем Config.php чтобы были доступны все классы конфигурации
require_once __DIR__ . '/src/Config.php';

use Kabiroman\Octawire\AuthService\Client\Config;
use Kabiroman\Octawire\AuthService\Client\KeyCache;
use Kabiroman\Octawire\AuthService\Client\RetryHandler;
use Kabiroman\Octawire\AuthService\Client\Model\PublicKeyInfo;

echo "=== PHP Client Test ===\n\n";

// Проверка зависимостей
echo "1. Checking dependencies...\n";
$grpcLoaded = extension_loaded('grpc');
echo "   - gRPC extension: " . ($grpcLoaded ? "✓ Loaded" : "✗ Not loaded") . "\n";

$protoGenerated = is_dir(__DIR__ . '/src/Generated');
echo "   - Proto classes: " . ($protoGenerated ? "✓ Generated" : "✗ Not generated") . "\n";

if (!$grpcLoaded || !$protoGenerated) {
    echo "\n⚠️  Warning: Full client functionality requires:\n";
    if (!$grpcLoaded) {
        echo "   - Install gRPC PHP extension: pecl install grpc\n";
    }
    if (!$protoGenerated) {
        echo "   - Generate proto classes: make generate-proto\n";
    }
    echo "\nTesting basic components only...\n\n";
}

// Тест 1: KeyCache
echo "2. Testing KeyCache...\n";
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
    
    if ($cached && $cached->keyId === $keyId) {
        echo "   ✓ KeyCache: get/set works\n";
    } else {
        echo "   ✗ KeyCache: get/set failed\n";
    }
    
    $active = $cache->getAllActive($projectId);
    if (count($active) === 1) {
        echo "   ✓ KeyCache: getAllActive works\n";
    } else {
        echo "   ✗ KeyCache: getAllActive failed\n";
    }
    
    $cache->invalidate($projectId);
    $afterInvalidate = $cache->get($projectId, $keyId);
    if ($afterInvalidate === null) {
        echo "   ✓ KeyCache: invalidate works\n";
    } else {
        echo "   ✗ KeyCache: invalidate failed\n";
    }
} catch (\Exception $e) {
    echo "   ✗ KeyCache test failed: " . $e->getMessage() . "\n";
}

// Тест 2: RetryHandler
echo "\n3. Testing RetryHandler...\n";
try {
    $handler = new RetryHandler();
    
    // Успешное выполнение
    $result = $handler->execute(fn() => 'success');
    if ($result === 'success') {
        echo "   ✓ RetryHandler: successful execution works\n";
    } else {
        echo "   ✗ RetryHandler: successful execution failed\n";
    }
    
    // Неретраируемая ошибка
    $nonRetryable = false;
    try {
        $handler->execute(fn() => throw new \InvalidArgumentException('Non-retryable'));
    } catch (\InvalidArgumentException $e) {
        $nonRetryable = true;
    }
    if ($nonRetryable) {
        echo "   ✓ RetryHandler: non-retryable error handling works\n";
    } else {
        echo "   ✗ RetryHandler: non-retryable error handling failed\n";
    }
} catch (\Exception $e) {
    echo "   ✗ RetryHandler test failed: " . $e->getMessage() . "\n";
}

// Тест 3: Config
echo "\n4. Testing Config...\n";
try {
    $config = new Config([
        'address' => 'localhost:50051',
        'project_id' => 'test-project',
        'api_key' => 'test-api-key',
    ]);
    
    if ($config->address === 'localhost:50051' && 
        $config->projectId === 'test-project' &&
        $config->apiKey === 'test-api-key') {
        echo "   ✓ Config: creation and properties work\n";
    } else {
        echo "   ✗ Config: creation or properties failed\n";
    }
} catch (\Exception $e) {
    echo "   ✗ Config test failed: " . $e->getMessage() . "\n";
}

// Тест 4: Health Check (если доступен)
if ($grpcLoaded && $protoGenerated) {
    echo "\n5. Testing AuthClient connection...\n";
    try {
        $config = new Config(['address' => 'localhost:50051']);
        // Здесь будет реальный тест после генерации proto
        echo "   ⚠️  AuthClient test skipped (requires proto generation)\n";
    } catch (\Exception $e) {
        echo "   ✗ AuthClient test failed: " . $e->getMessage() . "\n";
    }
} else {
    echo "\n5. Skipping AuthClient test (requires gRPC extension and proto classes)\n";
}

// Проверка доступности сервиса
echo "\n6. Checking Auth Service availability...\n";
$healthUrl = 'http://localhost:9765/health';
$ch = curl_init($healthUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 2);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200 && $response) {
    $health = json_decode($response, true);
    if ($health && isset($health['healthy']) && $health['healthy']) {
        echo "   ✓ Auth Service is healthy\n";
        echo "   - Version: " . ($health['version'] ?? 'N/A') . "\n";
        echo "   - Uptime: " . ($health['uptime'] ?? 'N/A') . " seconds\n";
    } else {
        echo "   ✗ Auth Service health check failed\n";
    }
} else {
    echo "   ✗ Auth Service is not reachable at $healthUrl\n";
}

echo "\n=== Test Summary ===\n";
echo "Basic components (KeyCache, RetryHandler, Config) tested.\n";
if (!$grpcLoaded || !$protoGenerated) {
    echo "Full client testing requires:\n";
    if (!$grpcLoaded) {
        echo "  - gRPC PHP extension\n";
    }
    if (!$protoGenerated) {
        echo "  - Generated proto classes\n";
    }
}
echo "\n✓ Test completed!\n";

