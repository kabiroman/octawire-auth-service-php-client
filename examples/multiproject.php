<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Kabiroman\Octawire\AuthService\Client\Config;
use Kabiroman\Octawire\AuthService\Client\AuthClient;
use Kabiroman\Octawire\AuthService\Client\Exception\AuthException;

// Создаем конфигурацию без дефолтного project_id
$config = new Config([
    'address' => 'localhost:50051',
    // project_id не указан - будет передаваться в каждом запросе
]);

try {
    $client = new AuthClient($config);

    echo "=== Multi-Project Demo ===\n\n";

    // Проект 1
    echo "1. Working with Project 1...\n";
    try {
        $response1 = $client->issueToken([
            'user_id' => 'user-123',
            'project_id' => 'project-1',
            'access_token_ttl' => 3600,
        ]);
        echo "   Token issued for project-1\n";
        echo "   Key ID: " . ($response1['key_id'] ?? 'N/A') . "\n";
    } catch (AuthException $e) {
        echo "   Error: " . $e->getMessage() . "\n";
    }

    // Проект 2
    echo "\n2. Working with Project 2...\n";
    try {
        $response2 = $client->issueToken([
            'user_id' => 'user-456',
            'project_id' => 'project-2',
            'access_token_ttl' => 3600,
        ]);
        echo "   Token issued for project-2\n";
        echo "   Key ID: " . ($response2['key_id'] ?? 'N/A') . "\n";
    } catch (AuthException $e) {
        echo "   Error: " . $e->getMessage() . "\n";
    }

    // Получение публичных ключей для разных проектов
    echo "\n3. Getting public keys for different projects...\n";
    foreach (['project-1', 'project-2'] as $projectId) {
        try {
            $keyResponse = $client->getPublicKey([
                'project_id' => $projectId,
            ]);
            echo "   Project $projectId: Key ID " . ($keyResponse['key_id'] ?? 'N/A') . "\n";
        } catch (AuthException $e) {
            echo "   Project $projectId: Error - " . $e->getMessage() . "\n";
        }
    }

} catch (AuthException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
} finally {
    if (isset($client)) {
        $client->close();
    }
}

echo "\n✓ Multi-project demo completed!\n";

