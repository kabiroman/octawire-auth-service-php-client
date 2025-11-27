<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Kabiroman\Octawire\AuthService\Client\Config;
use Kabiroman\Octawire\AuthService\Client\AuthClient;
use Kabiroman\Octawire\AuthService\Client\Exception\AuthException;
use Kabiroman\Octawire\AuthService\Client\Request\JWT\IssueServiceTokenRequest;

echo "=== Connection Scenarios Examples ===\n\n";

// ============================================================================
// Сценарий 1: PROD + service_auth=false (TLS обязателен, без service auth)
// ============================================================================
echo "=== Scenario 1: PROD + service_auth=false ===\n";
echo "TLS обязателен (tcp.tls.enabled=true, tcp.tls.required=true), без service authentication\n\n";

$config1 = new Config([
    'transport' => 'tcp',
    'tcp' => [
        'host' => 'auth-service.example.com',
        'port' => 50052,
        'tls' => [
            'enabled' => true,
            'required' => true,
            'ca_file' => '/path/to/ca.crt',
            'server_name' => 'auth-service.example.com',
        ],
    ],
    'project_id' => 'default-project-id',
]);

try {
    $client1 = new AuthClient($config1);
    echo "✓ Client created for PROD without service auth\n";
    
    // Health check (публичный метод)
    $health = $client1->healthCheck();
    echo "✓ Health check: " . ($health->healthy ? 'healthy' : 'unhealthy') . "\n";
    
    $client1->close();
} catch (AuthException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// Сценарий 2: PROD + service_auth=true (TLS обязателен, с service auth)
// ============================================================================
echo "=== Scenario 2: PROD + service_auth=true ===\n";
echo "TLS обязателен + service authentication\n\n";

$config2 = new Config([
    'transport' => 'tcp',
    'tcp' => [
        'host' => 'auth-service.example.com',
        'port' => 50052,
        'tls' => [
            'enabled' => true,
            'required' => true,
            'ca_file' => '/path/to/ca.crt',
            'cert_file' => '/path/to/client.crt', // для mTLS
            'key_file' => '/path/to/client.key', // для mTLS
            'server_name' => 'auth-service.example.com',
        ],
    ],
    'project_id' => 'default-project-id',
    'service_secret' => $_ENV['IDENTITY_SERVICE_SECRET'] ?? 'identity-service-secret-abc123def456',
]);

try {
    $client2 = new AuthClient($config2);
    echo "✓ Client created for PROD with service auth\n";
    
    // Health check
    $health = $client2->healthCheck();
    echo "✓ Health check: " . ($health->healthy ? 'healthy' : 'unhealthy') . "\n";
    
    // IssueServiceToken с service authentication
    $request = new IssueServiceTokenRequest(
        sourceService: 'identity-service',
        projectId: 'default-project-id', // Обязательное поле (v0.9.3+)
        targetService: 'gateway-service',
        ttl: 3600,
    );
    
    // Service secret можно передать как параметр или использовать из конфигурации
    $serviceResponse = $client2->issueServiceToken($request);
    echo "✓ Service token issued successfully\n";
    echo "  Token: " . substr($serviceResponse->accessToken, 0, 50) . "...\n";
    
    $client2->close();
} catch (AuthException $e) {
    if ($e->getErrorCode() === 'AUTH_FAILED') {
        echo "✗ Authentication failed: Invalid service credentials\n";
    } else {
        echo "✗ Error: " . $e->getMessage() . "\n";
    }
}

echo "\n";

// ============================================================================
// Сценарий 3: DEV + service_auth=false (TLS опционален, без service auth)
// ============================================================================
echo "=== Scenario 3: DEV + service_auth=false ===\n";
echo "TLS опционален (tcp.tls.enabled=false), без service authentication\n\n";

$config3 = new Config([
    'transport' => 'tcp',
    'tcp' => [
        'host' => 'localhost',
        'port' => 50052,
        'tls' => [
            'enabled' => false, // TLS опционален в DEV
        ],
    ],
    'project_id' => 'default-project-id',
]);

try {
    $client3 = new AuthClient($config3);
    echo "✓ Client created for DEV without service auth\n";
    
    // Health check
    $health = $client3->healthCheck();
    echo "✓ Health check: " . ($health->healthy ? 'healthy' : 'unhealthy') . "\n";
    
    $client3->close();
} catch (AuthException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// Сценарий 4: DEV + service_auth=true (TLS опционален, с service auth)
// ============================================================================
echo "=== Scenario 4: DEV + service_auth=true ===\n";
echo "TLS опционален + service authentication\n\n";

$config4 = new Config([
    'transport' => 'tcp',
    'tcp' => [
        'host' => 'localhost',
        'port' => 50052,
        'tls' => [
            'enabled' => false, // TLS опционален в DEV
        ],
    ],
    'project_id' => 'default-project-id',
    'service_secret' => 'dev-service-secret-abc123', // для service authentication
]);

try {
    $client4 = new AuthClient($config4);
    echo "✓ Client created for DEV with service auth\n";
    
    // Health check
    $health = $client4->healthCheck();
    echo "✓ Health check: " . ($health->healthy ? 'healthy' : 'unhealthy') . "\n";
    
    // IssueServiceToken с service authentication
    $request = new IssueServiceTokenRequest(
        sourceService: 'identity-service',
        projectId: 'default-project-id', // Обязательное поле (v0.9.3+)
        targetService: 'gateway-service',
        ttl: 3600,
    );
    
    // Service secret можно передать как параметр или использовать из конфигурации
    // В этом примере используем из конфигурации
    $serviceResponse = $client4->issueServiceToken($request);
    echo "✓ Service token issued successfully\n";
    echo "  Token: " . substr($serviceResponse->accessToken, 0, 50) . "...\n";
    
    // Альтернативно: передать service secret как параметр
    $serviceResponse2 = $client4->issueServiceToken($request, 'dev-service-secret-abc123');
    echo "✓ Service token issued with explicit secret\n";
    
    $client4->close();
} catch (AuthException $e) {
    if ($e->getErrorCode() === 'AUTH_FAILED') {
        echo "✗ Authentication failed: Invalid service credentials\n";
        echo "  Error: " . $e->getMessage() . "\n";
    } else {
        echo "✗ Error: " . $e->getMessage() . "\n";
    }
}

echo "\n";
echo "=== All scenarios completed! ===\n";

