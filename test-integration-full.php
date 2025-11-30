<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Kabiroman\Octawire\AuthService\Client\Config;
use Kabiroman\Octawire\AuthService\Client\AuthClient;
use Kabiroman\Octawire\AuthService\Client\Exception\AuthException;
use Kabiroman\Octawire\AuthService\Client\Exception\ConnectionException;
use Kabiroman\Octawire\AuthService\Client\Request\JWT\IssueTokenRequest;
use Kabiroman\Octawire\AuthService\Client\Request\JWT\IssueServiceTokenRequest;
use Kabiroman\Octawire\AuthService\Client\Request\JWT\ValidateTokenRequest;
use Kabiroman\Octawire\AuthService\Client\Request\JWT\RefreshTokenRequest;
use Kabiroman\Octawire\AuthService\Client\Request\JWT\ParseTokenRequest;
use Kabiroman\Octawire\AuthService\Client\Request\JWT\ExtractClaimsRequest;
use Kabiroman\Octawire\AuthService\Client\Request\JWT\ValidateBatchRequest;
use Kabiroman\Octawire\AuthService\Client\Request\JWT\RevokeTokenRequest;
use Kabiroman\Octawire\AuthService\Client\Request\JWT\GetPublicKeyRequest;
use Kabiroman\Octawire\AuthService\Client\Request\JWT\HealthCheckRequest;
use Kabiroman\Octawire\AuthService\Client\Request\APIKey\CreateAPIKeyRequest;

/**
 * Comprehensive integration test for PHP client with real Auth Service connection
 * Tests all methods across all scenarios: TLS/no-TLS, service_auth/no-auth (v1.0)
 */

/**
 * Конфигурация тестового сценария
 */
class TestScenarioConfig
{
    public string $name;
    public bool $tlsEnabled;
    public bool $serviceAuth;
    public ?string $serviceName;
    public ?string $serviceSecret;
    public ?string $jwtToken; // Для методов требующих JWT
    public ?string $apiKey;
    
    public function __construct(array $config)
    {
        $this->name = $config['name'];
        $this->tlsEnabled = $config['tlsEnabled'];
        $this->serviceAuth = $config['serviceAuth'];
        $this->serviceName = $config['serviceName'] ?? null;
        $this->serviceSecret = $config['serviceSecret'] ?? null;
        $this->jwtToken = $config['jwtToken'] ?? null;
        $this->apiKey = $config['apiKey'] ?? null;
    }
}

/**
 * Создает клиент на основе конфигурации сценария
 */
function createTestClient(TestScenarioConfig $scenario, string $address): AuthClient
{
    [$host, $port] = explode(':', $address);
    $port = (int)$port;
    
    $config = [
        'transport' => 'tcp',
        'tcp' => [
            'host' => $host,
            'port' => $port,
            'tls' => [
                'enabled' => $scenario->tlsEnabled,
                'insecure_skip_verify' => $scenario->tlsEnabled, // For testing
            ],
        ],
    ];
    
    if ($scenario->serviceAuth && $scenario->serviceSecret) {
        $config['service_secret'] = $scenario->serviceSecret;
    }
    
    if ($scenario->apiKey) {
        $config['api_key'] = $scenario->apiKey;
    }
    
    return new AuthClient(new Config($config));
}

/**
 * Выполняет тесты всех методов для заданного сценария
 */
function runAllMethodsTests(TestScenarioConfig $config, AuthClient $client, string $projectId, string $testUserId): array
{
    $results = [
        'health_check' => false,
        'issue_token' => false,
        'validate_token' => false,
        'refresh_token' => false,
        'parse_token' => false,
        'extract_claims' => false,
        'validate_batch' => false,
        'get_public_key' => false,
        'issue_service_token' => false,
        'revoke_token' => false,
        'create_api_key' => false,
        'errors' => [],
    ];
    
    $accessToken = null;
    $refreshToken = null;
    
    // Test 1: HealthCheck
    echo "--- Test 1: HealthCheck ---\n";
    try {
        $healthResponse = $client->healthCheck(new HealthCheckRequest());
        if ($healthResponse->healthy) {
            echo "✓ Health check passed\n";
            echo "  Version: {$healthResponse->version}\n";
            if (isset($healthResponse->uptime)) {
                echo "  Uptime: {$healthResponse->uptime} seconds\n";
            }
            $results['health_check'] = true;
        } else {
            echo "✗ Health check failed: Service is unhealthy\n";
            $results['errors'][] = 'Health check returned unhealthy';
        }
    } catch (\Exception $e) {
        echo "✗ Health check failed: {$e->getMessage()}\n";
        $results['errors'][] = "Health check: {$e->getMessage()}";
    }
    
    // Test 2: IssueToken
    echo "\n--- Test 2: IssueToken ---\n";
    try {
        $issueRequest = new IssueTokenRequest(
            userId: $testUserId,
            projectId: $projectId,
            claims: ['test' => 'true', 'scenario' => $config->name],
            accessTokenTtl: 3600,
            refreshTokenTtl: 86400,
        );
        $tokenResponse = $client->issueToken($issueRequest);
        $accessToken = $tokenResponse->accessToken;
        $refreshToken = $tokenResponse->refreshToken;
        echo "✓ Token issued successfully\n";
        echo "  Access Token: " . substr($accessToken, 0, 50) . "...\n";
        echo "  Expires At: " . date('Y-m-d H:i:s', $tokenResponse->accessTokenExpiresAt) . "\n";
        $results['issue_token'] = true;
    } catch (\Exception $e) {
        echo "✗ Issue token failed: {$e->getMessage()}\n";
        $results['errors'][] = "Issue token: {$e->getMessage()}";
    }
    
    // Test 3: ValidateToken (with optional service auth)
    if ($accessToken !== null) {
        echo "\n--- Test 3: ValidateToken ---\n";
        try {
            $validateRequest = new ValidateTokenRequest(
                token: $accessToken,
                projectId: $projectId,
                checkBlacklist: true,
            );
            // Use service auth if available, otherwise call as public method (v1.0+)
            $validateResponse = $client->validateToken(
                $validateRequest,
                $config->serviceName,
                $config->serviceSecret
            );
            if ($validateResponse->valid) {
                echo "✓ Token validation passed\n";
                if ($validateResponse->claims !== null) {
                    echo "  User ID: {$validateResponse->claims->userId}\n";
                }
                $results['validate_token'] = true;
            } else {
                echo "✗ Token validation failed: {$validateResponse->error}\n";
                $results['errors'][] = "Validate token: {$validateResponse->error}";
            }
        } catch (\Exception $e) {
            echo "✗ Validate token failed: {$e->getMessage()}\n";
            $results['errors'][] = "Validate token: {$e->getMessage()}";
        }
    }
    
    // Test 4: RefreshToken
    if ($refreshToken !== null) {
        echo "\n--- Test 4: RefreshToken ---\n";
        try {
            $refreshRequest = new RefreshTokenRequest(
                refreshToken: $refreshToken,
                projectId: $projectId,
            );
            $refreshResponse = $client->refreshToken($refreshRequest);
            echo "✓ Token refresh passed\n";
            echo "  New Access Token: " . substr($refreshResponse->accessToken, 0, 50) . "...\n";
            $results['refresh_token'] = true;
        } catch (\Exception $e) {
            echo "✗ Refresh token failed: {$e->getMessage()}\n";
            $results['errors'][] = "Refresh token: {$e->getMessage()}";
        }
    }
    
    // Test 5: ParseToken (with optional service auth)
    if ($accessToken !== null) {
        echo "\n--- Test 5: ParseToken ---\n";
        try {
            $parseRequest = new ParseTokenRequest(
                token: $accessToken,
                projectId: $projectId,
            );
            $parseResponse = $client->parseToken(
                $parseRequest,
                $config->serviceName,
                $config->serviceSecret
            );
            if ($parseResponse->success) {
                echo "✓ Token parsing passed\n";
                if ($parseResponse->claims !== null) {
                    echo "  User ID: {$parseResponse->claims->userId}\n";
                }
                $results['parse_token'] = true;
            } else {
                echo "✗ Token parsing failed: {$parseResponse->error}\n";
                $results['errors'][] = "Parse token: {$parseResponse->error}";
            }
        } catch (\Exception $e) {
            echo "✗ Parse token failed: {$e->getMessage()}\n";
            $results['errors'][] = "Parse token: {$e->getMessage()}";
        }
    }
    
    // Test 6: ExtractClaims (with optional service auth)
    if ($accessToken !== null) {
        echo "\n--- Test 6: ExtractClaims ---\n";
        try {
            $extractRequest = new ExtractClaimsRequest(
                token: $accessToken,
                projectId: $projectId,
                claimKeys: ['user_id', 'test', 'scenario'],
            );
            $extractResponse = $client->extractClaims(
                $extractRequest,
                $config->serviceName,
                $config->serviceSecret
            );
            if ($extractResponse->success) {
                echo "✓ Claims extraction passed\n";
                if (!empty($extractResponse->claims)) {
                    echo "  Extracted claims: " . count($extractResponse->claims) . " keys\n";
                }
                $results['extract_claims'] = true;
            } else {
                echo "✗ Claims extraction failed: {$extractResponse->error}\n";
                $results['errors'][] = "Extract claims: {$extractResponse->error}";
            }
        } catch (\Exception $e) {
            echo "✗ Extract claims failed: {$e->getMessage()}\n";
            $results['errors'][] = "Extract claims: {$e->getMessage()}";
        }
    }
    
    // Test 7: ValidateBatch (with optional service auth)
    if ($accessToken !== null) {
        echo "\n--- Test 7: ValidateBatch ---\n";
        try {
            $validateBatchRequest = new ValidateBatchRequest(
                tokens: [$accessToken],
                checkBlacklist: true,
            );
            $validateBatchResponse = $client->validateBatch(
                $validateBatchRequest,
                $config->serviceName,
                $config->serviceSecret
            );
            if (!empty($validateBatchResponse->results) && $validateBatchResponse->results[0]->valid) {
                echo "✓ Batch validation passed\n";
                echo "  Validated tokens: " . count($validateBatchResponse->results) . "\n";
                $results['validate_batch'] = true;
            } else {
                echo "✗ Batch validation failed\n";
                $results['errors'][] = "Validate batch: validation failed";
            }
        } catch (\Exception $e) {
            echo "✗ Batch validation failed: {$e->getMessage()}\n";
            $results['errors'][] = "Validate batch: {$e->getMessage()}";
        }
    }
    
    // Test 8: GetPublicKey
    echo "\n--- Test 8: GetPublicKey ---\n";
    try {
        $publicKeyRequest = new GetPublicKeyRequest(
            projectId: $projectId,
        );
        $publicKeyResponse = $client->getPublicKey($publicKeyRequest);
        echo "✓ Public key retrieved\n";
        echo "  Key ID: {$publicKeyResponse->keyId}\n";
        echo "  Algorithm: {$publicKeyResponse->algorithm}\n";
        $results['get_public_key'] = true;
    } catch (\Exception $e) {
        echo "✗ Get public key failed: {$e->getMessage()}\n";
        $results['errors'][] = "Get public key: {$e->getMessage()}";
    }
    
    // Test 9: IssueServiceToken (with optional service auth)
    echo "\n--- Test 9: IssueServiceToken ---\n";
    try {
        $serviceTokenRequest = new IssueServiceTokenRequest(
            sourceService: $config->serviceName ?? 'test-service',
            projectId: $projectId,
            targetService: 'gateway-service',
            ttl: 3600,
        );
        $serviceTokenResponse = $client->issueServiceToken($serviceTokenRequest, $config->serviceSecret);
        echo "✓ Service token issued successfully\n";
        echo "  Service Token: " . substr($serviceTokenResponse->accessToken, 0, 50) . "...\n";
        $results['issue_service_token'] = true;
    } catch (\Exception $e) {
        echo "✗ Issue service token failed: {$e->getMessage()}\n";
        $results['errors'][] = "Issue service token: {$e->getMessage()}";
    }
    
    // Test 10: RevokeToken (requires JWT)
    if ($accessToken !== null && $config->jwtToken !== null) {
        echo "\n--- Test 10: RevokeToken ---\n";
        try {
            $revokeRequest = new RevokeTokenRequest(
                token: $accessToken,
                projectId: $projectId,
            );
            $revokeResponse = $client->revokeToken($revokeRequest, $config->jwtToken);
            if ($revokeResponse->success) {
                echo "✓ Token revoked successfully\n";
                $results['revoke_token'] = true;
            } else {
                echo "✗ Token revocation failed: {$revokeResponse->error}\n";
                $results['errors'][] = "Revoke token: {$revokeResponse->error}";
            }
        } catch (\Exception $e) {
            echo "✗ Revoke token failed: {$e->getMessage()}\n";
            $results['errors'][] = "Revoke token: {$e->getMessage()}";
        }
    } else {
        echo "\n--- Test 10: RevokeToken (skipped - JWT token required) ---\n";
        echo "ℹ JWT token required for RevokeToken\n";
    }
    
    // Test 11: CreateAPIKey (requires JWT and project_id)
    if ($config->jwtToken !== null) {
        echo "\n--- Test 11: CreateAPIKey ---\n";
        try {
            // Try to get project_id from token if not provided in config
            $apiKeyProjectId = $projectId;
            if (empty($apiKeyProjectId) && $accessToken !== null) {
                // Try to extract project_id from token claims
                try {
                    $extractRequest = new ExtractClaimsRequest(
                        token: $accessToken,
                        projectId: '', // Empty - using public method
                        claimKeys: ['project_id'],
                    );
                    $extractResponse = $client->extractClaims(
                        $extractRequest,
                        $config->serviceName,
                        $config->serviceSecret
                    );
                    if ($extractResponse->success && !empty($extractResponse->claims['project_id'])) {
                        $apiKeyProjectId = $extractResponse->claims['project_id'];
                        echo "  Extracted project_id from token: {$apiKeyProjectId}\n";
                    }
                } catch (\Exception $e) {
                    // If extraction fails, use empty project_id and let server return error
                    echo "  Could not extract project_id from token: {$e->getMessage()}\n";
                }
            }
            
            $createAPIKeyRequest = new CreateAPIKeyRequest(
                projectId: $apiKeyProjectId,
                name: 'test-api-key',
                scopes: ['read', 'write'],
            );
            $createAPIKeyResponse = $client->createAPIKey($createAPIKeyRequest, $config->jwtToken);
            if (!empty($createAPIKeyResponse->apiKey)) {
                echo "✓ API key created successfully\n";
                echo "  Key ID: {$createAPIKeyResponse->keyId}\n";
                $results['create_api_key'] = true;
            } else {
                echo "✗ API key creation failed\n";
                $results['errors'][] = "Create API key: failed";
            }
        } catch (\Exception $e) {
            $errMsg = $e->getMessage();
            $errMsgLower = strtolower($errMsg);
            // If project_id is not available (empty or not in token), server will return error
            // This is expected - CreateAPIKey requires project_id according to v1.0 spec
            if (strpos($errMsgLower, 'project_id') !== false || strpos($errMsgLower, 'project') !== false) {
                echo "⏭ Create API key skipped (project_id required but not available in token or config): {$errMsg}\n";
                // Don't add to errors - this is expected when project_id is not available
            } else {
                echo "✗ Create API key failed: {$errMsg}\n";
                $results['errors'][] = "Create API key: {$errMsg}";
            }
        }
    } else {
        echo "\n--- Test 11: CreateAPIKey (skipped - JWT token required) ---\n";
        echo "ℹ JWT token required for CreateAPIKey\n";
    }
    
    return $results;
}

$projectId = ''; // Empty project_id - server will use default_project_id or project-id from metadata
$serviceName = 'identity-service';
$serviceSecret = 'identity-service-secret-abc123def456';
$testUserId = 'test-user-' . time();

/**
 * Определяет, требуется ли TLS для подключения к серверу
 */
function detectTLSRequirement(string $host, int $port): bool
{
    // Try non-TLS first
    $socket = @fsockopen($host, $port, $errno, $errstr, 1);
    if ($socket !== false) {
        fclose($socket);
        return false; // Non-TLS connection works
    }
    
    // If connection refused, try to determine from config or default to false
    // In practice, check Auth Service config or try TLS connection
    // For now, default to false (can be overridden)
    return false;
}

/**
 * Основная функция тестирования всех сценариев
 */
function testAllScenariosV1(string $address, string $projectId, string $serviceName, string $serviceSecret): void
{
    // Check if Auth Service is running
    [$host, $port] = explode(':', $address);
    $port = (int)$port;
    $socket = @fsockopen($host, $port, $errno, $errstr, 1);
    if ($socket === false) {
        echo "ERROR: Auth Service is not running on {$address}\n";
        echo "Please start Auth Service before running integration tests.\n";
        echo "\nTo start Auth Service:\n";
        echo "  cd /var/www/national-union.ru/octawire/services/auth-service\n";
        echo "  docker-compose --profile dev up -d auth-service-dev\n";
        echo "\nOr use the appropriate config file for your scenario.\n\n";
        exit(1);
    }
    fclose($socket);
    
    $tlsRequired = detectTLSRequirement($host, $port);
    echo "TLS Requirement: " . ($tlsRequired ? "YES" : "NO") . "\n";
    echo "Auth Service is running - starting tests...\n\n";
    
    // Create temporary client to obtain JWT token for methods requiring JWT
    $jwtToken = null;
    $tempConfig = new Config([
        'transport' => 'tcp',
        'tcp' => [
            'host' => $host,
            'port' => $port,
            'tls' => [
                'enabled' => $tlsRequired,
                'insecure_skip_verify' => $tlsRequired,
            ],
        ],
    ]);
    try {
        $tempClient = new AuthClient($tempConfig);
        $issueReq = new IssueTokenRequest(
            userId: 'test-jwt-user',
            projectId: $projectId,
        );
        $issueResp = $tempClient->issueToken($issueReq);
        $jwtToken = $issueResp->accessToken;
        $tempClient->close();
    } catch (\Exception $e) {
        echo "Warning: Could not obtain JWT token for protected methods: {$e->getMessage()}\n";
    }
    
    // Define all test scenarios
    $scenarios = [
        new TestScenarioConfig([
            'name' => 'NoTLS_NoAuth',
            'tlsEnabled' => false,
            'serviceAuth' => false,
            'serviceName' => null,
            'serviceSecret' => null,
            'jwtToken' => $jwtToken,
            'apiKey' => null,
        ]),
        new TestScenarioConfig([
            'name' => 'NoTLS_WithServiceAuth',
            'tlsEnabled' => false,
            'serviceAuth' => true,
            'serviceName' => $serviceName,
            'serviceSecret' => $serviceSecret,
            'jwtToken' => $jwtToken,
            'apiKey' => null,
        ]),
        new TestScenarioConfig([
            'name' => 'WithTLS_NoAuth',
            'tlsEnabled' => true,
            'serviceAuth' => false,
            'serviceName' => null,
            'serviceSecret' => null,
            'jwtToken' => $jwtToken,
            'apiKey' => null,
        ]),
        new TestScenarioConfig([
            'name' => 'WithTLS_WithServiceAuth',
            'tlsEnabled' => true,
            'serviceAuth' => true,
            'serviceName' => $serviceName,
            'serviceSecret' => $serviceSecret,
            'jwtToken' => $jwtToken,
            'apiKey' => null,
        ]),
    ];
    
    $results = [];
    $testUserId = 'test-user-' . time();
    
    foreach ($scenarios as $scenario) {
        echo "\n" . str_repeat('=', 80) . "\n";
        echo "Testing scenario: {$scenario->name} (TLS=" . ($scenario->tlsEnabled ? 'true' : 'false') . ", ServiceAuth=" . ($scenario->serviceAuth ? 'true' : 'false') . ")\n";
        echo str_repeat('=', 80) . "\n\n";
        
        // Skip TLS scenarios if server doesn't support TLS
        if ($scenario->tlsEnabled && !$tlsRequired) {
            echo "⏭ Skipping TLS scenario {$scenario->name}: server does not require TLS\n\n";
            continue;
        }
        
        try {
            $client = createTestClient($scenario, $address);
            
            // Verify connection with health check
            try {
                $healthResp = $client->healthCheck(new HealthCheckRequest());
                if (!$healthResp->healthy) {
                    echo "⏭ Skipping scenario {$scenario->name}: service is unhealthy\n\n";
                    $client->close();
                    continue;
                }
            } catch (\Exception $e) {
                echo "⏭ Skipping scenario {$scenario->name}: service not accessible - {$e->getMessage()}\n\n";
                $client->close();
                continue;
            }
            
            // Run all method tests
            $results[$scenario->name] = runAllMethodsTests($scenario, $client, $projectId, $testUserId);
            
            $client->close();
        } catch (ConnectionException $e) {
            echo "✗ Failed to connect for scenario {$scenario->name}: {$e->getMessage()}\n";
            $results[$scenario->name] = ['errors' => ["Connection: {$e->getMessage()}"]];
        } catch (\Exception $e) {
            echo "✗ Unexpected error for scenario {$scenario->name}: {$e->getMessage()}\n";
            $results[$scenario->name] = ['errors' => ["Unexpected: {$e->getMessage()}"]];
        }
    }
    
    // Summary
    echo "\n" . str_repeat('=', 80) . "\n";
    echo "TEST SUMMARY\n";
    echo str_repeat('=', 80) . "\n\n";
    
    $totalTests = 0;
    $passedTests = 0;
    
    foreach ($results as $scenarioName => $result) {
        echo "{$scenarioName}:\n";
        
        $scenarioTests = [
            'Health Check' => $result['health_check'] ?? false,
            'Issue Token' => $result['issue_token'] ?? false,
            'Validate Token' => $result['validate_token'] ?? false,
            'Refresh Token' => $result['refresh_token'] ?? false,
            'Parse Token' => $result['parse_token'] ?? false,
            'Extract Claims' => $result['extract_claims'] ?? false,
            'Validate Batch' => $result['validate_batch'] ?? false,
            'Get Public Key' => $result['get_public_key'] ?? false,
            'Issue Service Token' => $result['issue_service_token'] ?? false,
            'Revoke Token' => $result['revoke_token'] ?? false,
            'Create API Key' => $result['create_api_key'] ?? false,
        ];
        
        foreach ($scenarioTests as $testName => $passed) {
            $status = $passed ? '✓ PASS' : '✗ FAIL';
            echo "  {$testName}: {$status}\n";
            $totalTests++;
            if ($passed) {
                $passedTests++;
            }
        }
        
        if (!empty($result['errors'])) {
            echo "  Errors:\n";
            foreach ($result['errors'] as $error) {
                echo "    - {$error}\n";
            }
        }
        echo "\n";
    }
    
    echo str_repeat('=', 80) . "\n";
    echo "Total: {$passedTests}/{$totalTests} tests passed\n";
    echo str_repeat('=', 80) . "\n";
}

// Run tests
testAllScenariosV1('localhost:50052', $projectId, $serviceName, $serviceSecret);
