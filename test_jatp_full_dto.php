<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Kabiroman\Octawire\AuthService\Client\Config;
use Kabiroman\Octawire\AuthService\Client\AuthClient;
use Kabiroman\Octawire\AuthService\Client\Request\JWT as JWTRequest;
use Kabiroman\Octawire\AuthService\Client\Response\JWT as JWTResponse;
use Kabiroman\Octawire\AuthService\Client\Request\APIKey as APIKeyRequest;
use Kabiroman\Octawire\AuthService\Client\Response\APIKey as APIKeyResponse;
use Kabiroman\Octawire\AuthService\Client\Exception\AuthException;

echo "=== PHP JATP Client Full Test Suite (with DTO) ===\n\n";

// Конфигурация для подключения к локальному сервису
$config = new Config([
    'transport' => 'tcp',
    'tcp' => [
        'host' => 'localhost',
        'port' => 50052,
        'tls' => [
            'enabled' => false,
        ],
        'persistent' => true,
    ],
    'project_id' => 'test-project',
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

// Service authentication credentials
$serviceName = 'test-service';
$serviceSecret = 'test-service-secret-123';

// ============================================================================
// TEST 1: HealthCheck (public, no auth required)
// ============================================================================
echo "--- Test 1: HealthCheck (public) ---\n";
try {
    $response = $client->healthCheck();
    echo "✓ Health check successful\n";
    echo "  Healthy: " . ($response->healthy ? 'Yes' : 'No') . "\n";
    echo "  Version: " . ($response->version ?? 'N/A') . "\n";
    if ($response->uptime !== null) {
        echo "  Uptime: " . $response->uptime . " seconds\n";
    }
} catch (AuthException $e) {
    echo "✗ Health check failed: " . $e->getMessage() . "\n";
    $client->close();
    exit(1);
}
echo "\n";

// ============================================================================
// TEST 2: GetPublicKey (public, no auth required)
// ============================================================================
echo "--- Test 2: GetPublicKey (public) ---\n";
$publicKeyInfo = null;
try {
    $request = new JWTRequest\GetPublicKeyRequest(
        projectId: 'test-project'
    );
    
    $response = $client->getPublicKey($request);
    
    if ($response->publicKeyPem) {
        echo "✓ Public key retrieved successfully\n";
        echo "  Key ID: " . $response->keyId . "\n";
        echo "  Algorithm: " . $response->algorithm . "\n";
        echo "  Active Keys: " . count($response->activeKeys) . "\n";
        $publicKeyInfo = $response;
    } else {
        echo "✗ GetPublicKey failed: missing key in response\n";
    }
} catch (AuthException $e) {
    echo "✗ GetPublicKey failed: " . $e->getMessage() . "\n";
}
echo "\n";

// ============================================================================
// TEST 3: IssueToken
// ============================================================================
echo "--- Test 3: IssueToken ---\n";
$accessToken = null;
$refreshToken = null;
try {
    $request = new JWTRequest\IssueTokenRequest(
        userId: 'test-user-123',
        claims: ['role' => 'admin', 'email' => 'test@example.com'],
        accessTokenTtl: 3600,
        refreshTokenTtl: 86400,
        projectId: 'test-project'
    );
    
    $response = $client->issueToken($request);
    
    $accessToken = $response->accessToken;
    $refreshToken = $response->refreshToken;

    if ($accessToken && $refreshToken) {
        echo "✓ Token issued successfully\n";
        echo "  Access Token: " . substr($accessToken, 0, 50) . "...\n";
        echo "  Refresh Token: " . substr($refreshToken, 0, 50) . "...\n";
        echo "  Key ID: " . $response->keyId . "\n";
    } else {
        echo "✗ Token issue failed: missing tokens in response\n";
    }
} catch (AuthException $e) {
    echo "✗ IssueToken failed: " . $e->getMessage() . "\n";
}
echo "\n";

// ============================================================================
// TEST 4: ValidateToken (requires JWT token for authentication)
// ============================================================================
if ($accessToken) {
    echo "--- Test 4: ValidateToken (with JWT auth) ---\n";
    try {
        $request = new JWTRequest\ValidateTokenRequest(
            token: $accessToken,
            checkBlacklist: false
        );
        
        $response = $client->validateToken($request, $accessToken);

        if ($response->valid) {
            echo "✓ Token validation successful\n";
            if ($response->claims !== null) {
                echo "  User ID: " . $response->claims->userId . "\n";
                echo "  Issuer: " . $response->claims->issuer . "\n";
                echo "  Token Type: " . $response->claims->tokenType . "\n";
            }
        } else {
            echo "✗ Token validation failed\n";
        }
    } catch (AuthException $e) {
        echo "✗ ValidateToken failed: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

// ============================================================================
// TEST 5: ParseToken (requires JWT token for authentication)
// ============================================================================
if ($accessToken) {
    echo "--- Test 5: ParseToken (with JWT auth) ---\n";
    try {
        $request = new JWTRequest\ParseTokenRequest(
            token: $accessToken
        );
        
        $response = $client->parseToken($request, $accessToken);

        if ($response->success && $response->claims !== null) {
            echo "✓ Token parsed successfully\n";
            echo "  Claims count: " . count($response->claims->customClaims) . " custom claims\n";
            echo "  User ID: " . $response->claims->userId . "\n";
            echo "  Role: " . ($response->claims->getClaim('role') ?? 'N/A') . "\n";
        } else {
            echo "✗ ParseToken failed: " . ($response->error ?? 'unknown error') . "\n";
        }
    } catch (AuthException $e) {
        echo "✗ ParseToken failed: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

// ============================================================================
// TEST 6: ExtractClaims (requires JWT token for authentication)
// ============================================================================
if ($accessToken) {
    echo "--- Test 6: ExtractClaims (with JWT auth) ---\n";
    try {
        $request = new JWTRequest\ExtractClaimsRequest(
            token: $accessToken
        );
        
        $response = $client->extractClaims($request, $accessToken);

        if ($response->success) {
            echo "✓ Claims extracted successfully\n";
            echo "  Email: " . ($response->claims['email'] ?? 'N/A') . "\n";
            echo "  Role: " . ($response->claims['role'] ?? 'N/A') . "\n";
        } else {
            echo "✗ ExtractClaims failed: " . ($response->error ?? 'unknown error') . "\n";
        }
    } catch (AuthException $e) {
        echo "✗ ExtractClaims failed: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

// ============================================================================
// TEST 7: RefreshToken
// ============================================================================
if ($refreshToken) {
    echo "--- Test 7: RefreshToken ---\n";
    try {
        $request = new JWTRequest\RefreshTokenRequest(
            refreshToken: $refreshToken
        );
        
        $response = $client->refreshToken($request);

        if ($response->accessToken) {
            echo "✓ Token refresh successful\n";
            echo "  New Access Token: " . substr($response->accessToken, 0, 50) . "...\n";
            $accessToken = $response->accessToken; // Update for next tests
        } else {
            echo "✗ RefreshToken failed: missing token in response\n";
        }
    } catch (AuthException $e) {
        echo "✗ RefreshToken failed: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

// ============================================================================
// TEST 8: ValidateBatch (requires JWT token for authentication)
// ============================================================================
if ($accessToken && $refreshToken) {
    echo "--- Test 8: ValidateBatch (with JWT auth) ---\n";
    try {
        $request = new JWTRequest\ValidateBatchRequest(
            tokens: [$accessToken, $refreshToken],
            checkBlacklist: false
        );
        
        $response = $client->validateBatch($request, $accessToken);

        if (!empty($response->results)) {
            echo "✓ Batch validation successful\n";
            echo "  Validated tokens: " . count($response->results) . "\n";
            foreach ($response->results as $idx => $result) {
                echo "  Token " . ($idx + 1) . ": " . ($result->valid ? "✓ valid" : "✗ invalid") . "\n";
            }
        } else {
            echo "✗ ValidateBatch failed: missing results\n";
        }
    } catch (AuthException $e) {
        echo "✗ ValidateBatch failed: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

// ============================================================================
// TEST 9: IssueServiceToken (requires service authentication)
// ============================================================================
echo "--- Test 9: IssueServiceToken (with service auth) ---\n";
$serviceToken = null;
try {
    $request = new JWTRequest\IssueServiceTokenRequest(
        sourceService: $serviceName,
        claims: ['scope' => 'internal'],
        ttl: 3600,
        projectId: 'test-project'
    );
    
    $response = $client->issueServiceToken($request, $serviceSecret);

    $serviceToken = $response->accessToken;
    if ($serviceToken) {
        echo "✓ Service token issued successfully\n";
        echo "  Service Token: " . substr($serviceToken, 0, 50) . "...\n";
    } else {
        echo "✗ IssueServiceToken failed: missing token in response\n";
    }
} catch (AuthException $e) {
    echo "✗ IssueServiceToken failed: " . $e->getMessage() . "\n";
}
echo "\n";

// ============================================================================
// TEST 10: RevokeToken (requires JWT token for authentication)
// ============================================================================
if ($accessToken) {
    echo "--- Test 10: RevokeToken (with JWT auth) ---\n";
    try {
        $request = new JWTRequest\RevokeTokenRequest(
            token: $accessToken
        );
        
        $response = $client->revokeToken($request, $accessToken);

        if ($response->success) {
            echo "✓ Token revoked successfully\n";
            $accessToken = null; // Token is now invalid
        } else {
            echo "✗ RevokeToken failed: " . ($response->error ?? 'unknown error') . "\n";
        }
    } catch (AuthException $e) {
        echo "✗ RevokeToken failed: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

// ============================================================================
// TEST 11: CreateAPIKey (requires JWT token for authentication)
// ============================================================================
if ($serviceToken) {
    echo "--- Test 11: CreateAPIKey (with JWT auth) ---\n";
    $apiKeyId = null;
    $apiKey = null;
    try {
        $request = new APIKeyRequest\CreateAPIKeyRequest(
            projectId: 'test-project',
            name: 'test-api-key-php-client-dto',
            scopes: ['read', 'write'],
            ttl: 3600 * 24 * 30 // 30 days
        );
        
        $response = $client->createAPIKey($request, $serviceToken);

        $apiKeyId = $response->keyId;
        $apiKey = $response->apiKey;
        if ($apiKeyId && $apiKey) {
            echo "✓ API key created successfully\n";
            echo "  Key ID: " . $apiKeyId . "\n";
            echo "  Key: " . substr($apiKey, 0, 50) . "...\n";
        } else {
            echo "✗ CreateAPIKey failed: missing key ID or key\n";
        }
    } catch (AuthException $e) {
        echo "✗ CreateAPIKey failed: " . $e->getMessage() . "\n";
        $apiKeyId = null;
        $apiKey = null;
    }
    echo "\n";

    // ============================================================================
    // TEST 12: ValidateAPIKey (requires JWT token for authentication)
    // ============================================================================
    if ($apiKey) {
        echo "--- Test 12: ValidateAPIKey (with JWT auth) ---\n";
        try {
            $request = new APIKeyRequest\ValidateAPIKeyRequest(
                apiKey: $apiKey
            );
            
            $response = $client->validateAPIKey($request, $serviceToken);

            if ($response->valid) {
                echo "✓ API key validated successfully\n";
                echo "  Key ID: " . ($response->projectId ?? 'N/A') . "\n";
                echo "  Scopes: " . implode(', ', $response->scopes) . "\n";
            } else {
                echo "✗ ValidateAPIKey failed: key not valid\n";
            }
        } catch (AuthException $e) {
            echo "✗ ValidateAPIKey failed: " . $e->getMessage() . "\n";
        }
        echo "\n";

        // ============================================================================
        // TEST 13: ListAPIKeys (requires JWT token for authentication)
        // ============================================================================
        echo "--- Test 13: ListAPIKeys (with JWT auth) ---\n";
        try {
            $request = new APIKeyRequest\ListAPIKeysRequest(
                projectId: 'test-project',
                page: 1,
                pageSize: 10
            );
            
            $response = $client->listAPIKeys($request, $serviceToken);

            if (!empty($response->keys)) {
                echo "✓ API keys listed successfully\n";
                echo "  Total keys: " . count($response->keys) . "\n";
                echo "  Total: " . $response->total . "\n";
                foreach ($response->keys as $key) {
                    echo "  - " . ($key->name ?? 'N/A') . " (ID: " . $key->keyId . ")\n";
                }
            } else {
                echo "✗ ListAPIKeys failed: missing keys\n";
            }
        } catch (AuthException $e) {
            echo "✗ ListAPIKeys failed: " . $e->getMessage() . "\n";
        }
        echo "\n";

        // ============================================================================
        // TEST 14: RevokeAPIKey (requires JWT token for authentication)
        // ============================================================================
        if ($apiKeyId) {
            echo "--- Test 14: RevokeAPIKey (with JWT auth) ---\n";
            try {
                $request = new APIKeyRequest\RevokeAPIKeyRequest(
                    projectId: 'test-project',
                    keyId: $apiKeyId
                );
                
                $response = $client->revokeAPIKey($request, $serviceToken);

                if ($response->success) {
                    echo "✓ API key revoked successfully\n";
                } else {
                    echo "✗ RevokeAPIKey failed: " . ($response->error ?? 'unknown error') . "\n";
                }
            } catch (AuthException $e) {
                echo "✗ RevokeAPIKey failed: " . $e->getMessage() . "\n";
            }
            echo "\n";
        }
    }
} else {
    echo "--- Test 11-14: API Key Management (skipped - service token required) ---\n";
    echo "  Note: Service authentication required for API key management\n";
    echo "\n";
}

$client->close();

echo "=== All tests completed! ===\n";
echo "\n";
echo "NOTE: All methods use typed DTOs according to JATP_METHODS_1.0.json specification\n";
echo "\n";

