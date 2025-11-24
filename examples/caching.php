<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Kabiroman\Octawire\AuthService\Client\Config;
use Kabiroman\Octawire\AuthService\Client\AuthClient;
use Kabiroman\Octawire\AuthService\Client\Exception\AuthException;

// Создаем конфигурацию с кэшированием ключей
$config = new Config([
    'address' => 'localhost:50051',
    'project_id' => 'project-1',
    'key_cache' => [
        'ttl' => 3600, // 1 час
        'max_size' => 100,
        'driver' => 'memory',
    ],
]);

try {
    $client = new AuthClient($config);
    $keyCache = $client->getKeyCache();

    echo "=== Key Caching Demo ===\n\n";

    // Получаем публичный ключ (будет закэширован)
    echo "1. Getting public key (will be cached)...\n";
    try {
        $response = $client->getPublicKey([
            'project_id' => 'project-1',
        ]);
        echo "   Key ID: " . ($response['key_id'] ?? 'N/A') . "\n";
        echo "   Algorithm: " . ($response['algorithm'] ?? 'N/A') . "\n";
    } catch (AuthException $e) {
        echo "   Error: " . $e->getMessage() . "\n";
    }

    // Получаем все активные ключи для проекта
    echo "\n2. Getting all active keys for project...\n";
    $activeKeys = $keyCache->getAllActive('project-1');
    echo "   Active keys count: " . count($activeKeys) . "\n";
    foreach ($activeKeys as $keyId => $keyInfo) {
        echo "   - Key ID: $keyId, Primary: " . ($keyInfo->isPrimary ? 'yes' : 'no') . "\n";
    }

    // Инвалидируем кэш
    echo "\n3. Invalidating cache...\n";
    $keyCache->invalidate('project-1');
    echo "   Cache invalidated\n";

    // Проверяем, что ключ больше не в кэше
    $cached = $keyCache->get('project-1', 'key-1');
    echo "   Key in cache: " . ($cached !== null ? 'yes' : 'no') . "\n";

    // Очищаем весь кэш
    echo "\n4. Clearing all cache...\n";
    $keyCache->clear();
    echo "   Cache cleared\n";

} catch (AuthException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
} finally {
    if (isset($client)) {
        $client->close();
    }
}

echo "\n✓ Caching demo completed!\n";

