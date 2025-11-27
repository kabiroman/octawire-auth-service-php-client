<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Kabiroman\Octawire\AuthService\Client\Config;
use Kabiroman\Octawire\AuthService\Client\AuthClient;
use Kabiroman\Octawire\AuthService\Client\Exception\AuthException;

// Создаем конфигурацию с TLS (используем tcp.tls формат)
$config = new Config([
    'transport' => 'tcp',
    'tcp' => [
        'host' => 'auth-service.example.com',
        'port' => 50052,
        'tls' => [
            'enabled' => true,
            'ca_file' => '/path/to/ca.crt',
            'cert_file' => '/path/to/client.crt', // Для mTLS
            'key_file' => '/path/to/client.key', // Для mTLS
            'server_name' => 'auth-service.example.com',
        ],
    ],
    'project_id' => 'default-project-id',
]);

// Создаем клиент
try {
    $client = new AuthClient($config);
    echo "✓ Client created with TLS\n";

    // Выполняем health check
    $healthResponse = $client->healthCheck();
    echo "Service is healthy: " . ($healthResponse['healthy'] ?? false ? 'true' : 'false') . "\n";
} catch (AuthException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
} finally {
    if (isset($client)) {
        $client->close();
    }
}

