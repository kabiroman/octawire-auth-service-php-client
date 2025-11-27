<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Kabiroman\Octawire\AuthService\Client\Config;
use Kabiroman\Octawire\AuthService\Client\AuthClient;
use Kabiroman\Octawire\AuthService\Client\Exception\AuthException;
use Kabiroman\Octawire\AuthService\Client\Request\JWT\IssueServiceTokenRequest;
use Kabiroman\Octawire\AuthService\Client\Request\JWT\HealthCheckRequest;

/**
 * Test script for all 4 connection scenarios:
 * 1. PROD + service_auth=false
 * 2. PROD + service_auth=true
 * 3. DEV + service_auth=false
 * 4. DEV + service_auth=true
 */

$scenarios = [
    [
        'name' => 'PROD + service_auth=false',
        'config' => [
            'transport' => 'tcp',
            'tcp' => [
                'host' => 'localhost',
                'port' => 50052,
                'tls' => [
                    'enabled' => false, // Simplified for testing without TLS certificates
                ],
            ],
            // 'project_id' => null, // Single-project config doesn't require project_id
        ],
        'service_auth' => false,
    ],
    [
        'name' => 'PROD + service_auth=true',
        'config' => [
            'transport' => 'tcp',
            'tcp' => [
                'host' => 'localhost',
                'port' => 50052,
                'tls' => [
                    'enabled' => false, // Simplified for testing without TLS certificates
                ],
            ],
            // 'project_id' => null, // Single-project config doesn't require project_id
            'service_secret' => 'identity-service-secret-abc123def456',
        ],
        'service_auth' => true,
    ],
    [
        'name' => 'DEV + service_auth=false',
        'config' => [
            'transport' => 'tcp',
            'tcp' => [
                'host' => 'localhost',
                'port' => 50052,
                'tls' => [
                    'enabled' => false,
                ],
            ],
            // 'project_id' => null, // Single-project config doesn't require project_id
        ],
        'service_auth' => false,
    ],
    [
        'name' => 'DEV + service_auth=true',
        'config' => [
            'transport' => 'tcp',
            'tcp' => [
                'host' => 'localhost',
                'port' => 50052,
                'tls' => [
                    'enabled' => false,
                ],
            ],
            // 'project_id' => null, // Single-project config doesn't require project_id
            'service_secret' => 'identity-service-secret-abc123def456',
        ],
        'service_auth' => true,
    ],
];

$results = [];

foreach ($scenarios as $scenario) {
    echo "\n" . str_repeat('=', 80) . "\n";
    echo "Testing: {$scenario['name']}\n";
    echo str_repeat('=', 80) . "\n\n";

    $results[$scenario['name']] = [
        'health_check' => false,
        'issue_token' => false,
        'issue_service_token' => false,
        'errors' => [],
    ];

    try {
        $config = new Config($scenario['config']);
        $client = new AuthClient($config);
        echo "✓ Client created successfully\n";

        // Test 1: Health Check
        echo "\n--- Test 1: Health Check ---\n";
        try {
            $healthResponse = $client->healthCheck(new HealthCheckRequest());
            if ($healthResponse->healthy) {
                echo "✓ Health check passed\n";
                echo "  Version: {$healthResponse->version}\n";
                if (isset($healthResponse->uptime)) {
                    echo "  Uptime: {$healthResponse->uptime} seconds\n";
                }
                $results[$scenario['name']]['health_check'] = true;
            } else {
                echo "✗ Health check failed: Service is unhealthy\n";
                $results[$scenario['name']]['errors'][] = 'Health check returned unhealthy';
            }
        } catch (AuthException $e) {
            echo "✗ Health check failed: {$e->getMessage()}\n";
            $results[$scenario['name']]['errors'][] = "Health check: {$e->getMessage()}";
        }

        // Test 2: Issue Token (public method)
        echo "\n--- Test 2: Issue Token (public method) ---\n";
        try {
            $issueTokenRequest = new \Kabiroman\Octawire\AuthService\Client\Request\JWT\IssueTokenRequest(
                userId: 'test-user-123',
                projectId: 'your-app-api', // Обязательное поле (v0.9.3+)
                claims: ['test' => 'true'],
                accessTokenTtl: 3600,
                refreshTokenTtl: 86400,
            );
            $tokenResponse = $client->issueToken($issueTokenRequest);
            echo "✓ Token issued successfully\n";
            echo "  Access Token: " . substr($tokenResponse->accessToken, 0, 50) . "...\n";
            echo "  Expires At: " . date('Y-m-d H:i:s', $tokenResponse->accessTokenExpiresAt) . "\n";
            $results[$scenario['name']]['issue_token'] = true;
        } catch (AuthException $e) {
            echo "✗ Issue token failed: {$e->getMessage()}\n";
            $results[$scenario['name']]['errors'][] = "Issue token: {$e->getMessage()}";
        }

        // Test 3: Issue Service Token (only if service_auth is enabled)
        if ($scenario['service_auth']) {
            echo "\n--- Test 3: Issue Service Token (service authentication) ---\n";
            try {
                $serviceTokenRequest = new IssueServiceTokenRequest(
                    sourceService: 'identity-service',
                    projectId: 'your-app-api', // Обязательное поле (v0.9.3+)
                    targetService: 'gateway-service',
                    ttl: 3600,
                );
                $serviceTokenResponse = $client->issueServiceToken($serviceTokenRequest);
                echo "✓ Service token issued successfully\n";
                echo "  Service Token: " . substr($serviceTokenResponse->accessToken, 0, 50) . "...\n";
                echo "  Expires At: " . date('Y-m-d H:i:s', $serviceTokenResponse->accessTokenExpiresAt) . "\n";
                $results[$scenario['name']]['issue_service_token'] = true;
            } catch (AuthException $e) {
                if ($e->getErrorCode() === 'AUTH_FAILED') {
                    echo "✗ Service authentication failed: {$e->getMessage()}\n";
                    echo "  Error Code: AUTH_FAILED\n";
                } else {
                    echo "✗ Issue service token failed: {$e->getMessage()}\n";
                }
                $results[$scenario['name']]['errors'][] = "Issue service token: {$e->getMessage()}";
            }

            // Test 4: Issue Service Token with wrong secret (should fail)
            echo "\n--- Test 4: Issue Service Token with wrong secret (should fail) ---\n";
            try {
                $serviceTokenRequest = new IssueServiceTokenRequest(
                    sourceService: 'identity-service',
                    projectId: 'your-app-api', // Обязательное поле (v0.9.3+)
                    targetService: 'gateway-service',
                    ttl: 3600,
                );
                $serviceTokenResponse = $client->issueServiceToken($serviceTokenRequest, 'wrong-secret');
                echo "✗ Service token issued with wrong secret (unexpected!)\n";
                $results[$scenario['name']]['errors'][] = 'Service token issued with wrong secret (should fail)';
            } catch (AuthException $e) {
                if ($e->getErrorCode() === 'AUTH_FAILED') {
                    echo "✓ Service authentication correctly rejected wrong secret\n";
                    echo "  Error Code: AUTH_FAILED\n";
                } else {
                    echo "✗ Unexpected error: {$e->getMessage()}\n";
                    $results[$scenario['name']]['errors'][] = "Unexpected error with wrong secret: {$e->getMessage()}";
                }
            }
        } else {
            echo "\n--- Test 3: Issue Service Token (skipped - service_auth disabled) ---\n";
            echo "ℹ Service authentication is disabled in this scenario\n";
        }

        $client->close();
    } catch (AuthException $e) {
        echo "✗ Failed to create client: {$e->getMessage()}\n";
        $results[$scenario['name']]['errors'][] = "Client creation: {$e->getMessage()}";
    } catch (\Exception $e) {
        echo "✗ Unexpected error: {$e->getMessage()}\n";
        $results[$scenario['name']]['errors'][] = "Unexpected: {$e->getMessage()}";
    }
}

// Summary
echo "\n" . str_repeat('=', 80) . "\n";
echo "TEST SUMMARY\n";
echo str_repeat('=', 80) . "\n\n";

foreach ($results as $scenarioName => $result) {
    echo "{$scenarioName}:\n";
    echo "  Health Check: " . ($result['health_check'] ? '✓ PASS' : '✗ FAIL') . "\n";
    echo "  Issue Token: " . ($result['issue_token'] ? '✓ PASS' : '✗ FAIL') . "\n";
    if (str_contains($scenarioName, 'service_auth=true')) {
        echo "  Issue Service Token: " . ($result['issue_service_token'] ? '✓ PASS' : '✗ FAIL') . "\n";
    }
    if (!empty($result['errors'])) {
        echo "  Errors:\n";
        foreach ($result['errors'] as $error) {
            echo "    - {$error}\n";
        }
    }
    echo "\n";
}

$totalTests = 0;
$passedTests = 0;
foreach ($results as $result) {
    $totalTests++;
    if ($result['health_check']) $passedTests++;
    $totalTests++;
    if ($result['issue_token']) $passedTests++;
    if (isset($result['issue_service_token'])) {
        $totalTests++;
        if ($result['issue_service_token']) $passedTests++;
    }
}

echo "Total: {$passedTests}/{$totalTests} tests passed\n";

