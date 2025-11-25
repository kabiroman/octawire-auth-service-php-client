<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Kabiroman\Octawire\AuthService\Client\Config;
use Kabiroman\Octawire\AuthService\Client\AuthClient;
use Kabiroman\Octawire\AuthService\Client\Exception\AuthException;

echo "=== PHP JATP Client Full Test Suite ===\n\n";

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
    'project_id' => null,
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

// Service authentication credentials (must match config.json service_auth.secrets)
$serviceName = 'test-service';
$serviceSecret = 'test-service-secret-123';

// ============================================================================
// TEST 1: HealthCheck (public, no auth required)
// ============================================================================
echo "--- Test 1: HealthCheck (public) ---\n";
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
    $response = $client->getPublicKey([]);
    $publicKey = $response['publicKeyPem'] ?? $response['public_key_pem'] ?? null;
    if ($publicKey) {
        echo "✓ Public key retrieved successfully\n";
        echo "  Key ID: " . ($response['keyId'] ?? $response['key_id'] ?? 'N/A') . "\n";
        echo "  Algorithm: " . ($response['algorithm'] ?? 'N/A') . "\n";
        $publicKeyInfo = $response;
    } else {
        echo "✗ GetPublicKey failed: missing key in response\n";
    }
} catch (AuthException $e) {
    echo "✗ GetPublicKey failed: " . $e->getMessage() . "\n";
}
echo "\n";

// ============================================================================
// TEST 3: IssueToken (no auth required, but may need project_id)
// ============================================================================
echo "--- Test 3: IssueToken ---\n";
$accessToken = null;
$refreshToken = null;
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
    }
} catch (AuthException $e) {
    echo "✗ IssueToken failed: " . $e->getMessage() . "\n";
    echo "  Note: This may require service authentication if auth_required is enabled\n";
}
echo "\n";

// ============================================================================
// TEST 4: ValidateToken (requires JWT token for authentication)
// ============================================================================
if ($accessToken) {
    echo "--- Test 4: ValidateToken (with JWT auth) ---\n";
    try {
        // Pass JWT token for authentication (in jwt_token field)
        $response = $client->validateToken([
            'token' => $accessToken,
            'check_blacklist' => false,
            'jwt_token' => $accessToken, // Auth token
        ]);

        if ($response['valid'] ?? false) {
            echo "✓ Token validation successful\n";
            if (isset($response['claims'])) {
                $claims = $response['claims'];
                echo "  User ID: " . ($claims['sub'] ?? $claims['user_id'] ?? 'N/A') . "\n";
                echo "  Issuer: " . ($claims['iss'] ?? $claims['issuer'] ?? 'N/A') . "\n";
                if (isset($claims['exp']) || isset($claims['expires_at'])) {
                    $exp = $claims['exp'] ?? $claims['expires_at'];
                    echo "  Expires At: " . date('Y-m-d H:i:s', (int)$exp) . "\n";
                }
            }
        } else {
            echo "✗ Token validation failed\n";
        }
    } catch (AuthException $e) {
        echo "✗ ValidateToken failed: " . $e->getMessage() . "\n";
        echo "  Note: ValidateToken requires JWT authentication\n";
    }
    echo "\n";
}

// ============================================================================
// TEST 5: ParseToken (requires JWT token for authentication)
// ============================================================================
if ($accessToken) {
    echo "--- Test 5: ParseToken (with JWT auth) ---\n";
    try {
        $response = $client->parseToken([
            'token' => $accessToken,
            'jwt_token' => $accessToken, // Auth token
        ]);

        if (isset($response['claims'])) {
            echo "✓ Token parsed successfully\n";
            echo "  Claims count: " . count($response['claims']) . "\n";
            $claims = $response['claims'];
            echo "  User ID: " . ($claims['sub'] ?? $claims['user_id'] ?? 'N/A') . "\n";
            echo "  Role: " . ($claims['role'] ?? 'N/A') . "\n";
        } else {
            echo "✗ ParseToken failed: missing claims\n";
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
        $response = $client->extractClaims([
            'token' => $accessToken,
            'jwt_token' => $accessToken, // Auth token
        ]);

        if (isset($response['claims'])) {
            echo "✓ Claims extracted successfully\n";
            $claims = $response['claims'];
            echo "  Email: " . ($claims['email'] ?? 'N/A') . "\n";
            echo "  Role: " . ($claims['role'] ?? 'N/A') . "\n";
        } else {
            echo "✗ ExtractClaims failed: missing claims\n";
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
        $response = $client->refreshToken([
            'refresh_token' => $refreshToken,
        ]);

        $newAccessToken = $response['accessToken'] ?? $response['access_token'] ?? null;
        if ($newAccessToken) {
            echo "✓ Token refresh successful\n";
            echo "  New Access Token: " . substr($newAccessToken, 0, 50) . "...\n";
            $accessToken = $newAccessToken; // Update for next tests
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
        $response = $client->validateBatch([
            'tokens' => [
                $accessToken,
                $refreshToken,
            ],
            'check_blacklist' => false,
            'jwt_token' => $accessToken, // Auth token
        ]);

        if (isset($response['results'])) {
            echo "✓ Batch validation successful\n";
            echo "  Validated tokens: " . count($response['results']) . "\n";
            foreach ($response['results'] as $idx => $result) {
                $valid = $result['valid'] ?? false;
                echo "  Token " . ($idx + 1) . ": " . ($valid ? "✓ valid" : "✗ invalid") . "\n";
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
    // Note: AuthClient::issueServiceToken handles service auth internally
    $response = $client->issueServiceToken([
        'service_name' => $serviceName,
        'service_secret' => $serviceSecret,
        'source_service' => $serviceName, // Required field
        'claims' => [
            'scope' => 'internal',
        ],
        'ttl' => 3600,
    ]);

    // IssueServiceToken returns accessToken (same format as IssueToken)
    $serviceToken = $response['accessToken'] ?? $response['access_token'] ?? $response['token'] ?? $response['serviceToken'] ?? null;
    if ($serviceToken) {
        echo "✓ Service token issued successfully\n";
        echo "  Service Token: " . substr($serviceToken, 0, 50) . "...\n";
    } else {
        echo "✗ IssueServiceToken failed: missing token in response\n";
        print_r($response);
    }
} catch (AuthException $e) {
    echo "✗ IssueServiceToken failed: " . $e->getMessage() . "\n";
    echo "  Note: Requires service_auth to be configured in auth-service config.json\n";
    echo "  Add to config.json:\n";
    echo "    \"service_auth\": {\n";
    echo "      \"enabled\": true,\n";
    echo "      \"secrets\": {\n";
    echo "        \"test-service\": \"test-service-secret-123\"\n";
    echo "      },\n";
    echo "      \"allowed_services\": [\"test-service\"]\n";
    echo "    }\n";
}
echo "\n";

// ============================================================================
// TEST 10: RevokeToken (requires JWT token for authentication)
// ============================================================================
if ($accessToken) {
    echo "--- Test 10: RevokeToken (with JWT auth) ---\n";
    try {
        $response = $client->revokeToken([
            'token' => $accessToken,
            'jwt_token' => $accessToken, // Auth token
            // Note: revoke_refresh is not supported, only token and optional ttl
        ]);

        if ($response['success'] ?? false) {
            echo "✓ Token revoked successfully\n";
            $accessToken = null; // Token is now invalid
        } else {
            $error = $response['error'] ?? 'unknown error';
            echo "✗ RevokeToken failed: " . $error . "\n";
        }
    } catch (AuthException $e) {
        echo "✗ RevokeToken failed: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

// ============================================================================
// TEST 11: CreateAPIKey (requires JWT token for authentication)
// Note: Requires project_id - skipping if not configured
// ============================================================================
if ($serviceToken && $config->projectId !== null) {
    echo "--- Test 11: CreateAPIKey (with JWT auth) ---\n";
    $apiKeyId = null;
    try {
        $response = $client->createAPIKey([
            'project_id' => $config->projectId,
            'name' => 'test-api-key-php-client',
            'scopes' => ['read', 'write'],
            'ttl' => 3600 * 24 * 30, // 30 days
            'jwt_token' => $serviceToken, // Auth token
        ]);

        $apiKeyId = $response['keyId'] ?? $response['key_id'] ?? null;
        if ($apiKeyId) {
            echo "✓ API key created successfully\n";
            echo "  Key ID: " . $apiKeyId . "\n";
            echo "  Key: " . substr($response['key'] ?? 'N/A', 0, 50) . "...\n";
        } else {
            echo "✗ CreateAPIKey failed: missing key ID\n";
        }
    } catch (AuthException $e) {
        echo "✗ CreateAPIKey failed: " . $e->getMessage() . "\n";
        $apiKeyId = null;
    }
    echo "\n";

    // ============================================================================
    // TEST 12: ValidateAPIKey (requires JWT token for authentication)
    // ============================================================================
    if (isset($apiKeyId) && $apiKeyId && isset($response['key'])) {
        echo "--- Test 12: ValidateAPIKey (with JWT auth) ---\n";
        try {
            $apiKey = $response['key'];
            $validateResponse = $client->validateAPIKey([
                'key' => $apiKey,
                'jwt_token' => $serviceToken, // Auth token
            ]);

            if ($validateResponse['valid'] ?? false) {
                echo "✓ API key validated successfully\n";
                echo "  Key ID: " . ($validateResponse['keyId'] ?? $validateResponse['key_id'] ?? 'N/A') . "\n";
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
            $listResponse = $client->listAPIKeys([
                'page' => 1,
                'page_size' => 10,
                'jwt_token' => $serviceToken, // Auth token
            ]);

            if (isset($listResponse['keys'])) {
                echo "✓ API keys listed successfully\n";
                echo "  Total keys: " . count($listResponse['keys']) . "\n";
                foreach ($listResponse['keys'] as $key) {
                    echo "  - " . ($key['name'] ?? 'N/A') . " (ID: " . ($key['keyId'] ?? $key['key_id'] ?? 'N/A') . ")\n";
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
                $revokeResponse = $client->revokeAPIKey([
                    'key_id' => $apiKeyId,
                    'jwt_token' => $serviceToken, // Auth token
                ]);

                if ($revokeResponse['revoked'] ?? false) {
                    echo "✓ API key revoked successfully\n";
                } else {
                    echo "✗ RevokeAPIKey failed: key not revoked\n";
                }
            } catch (AuthException $e) {
                echo "✗ RevokeAPIKey failed: " . $e->getMessage() . "\n";
            }
            echo "\n";
        }
    }
} else {
    echo "--- Test 11-14: API Key Management (skipped - project_id not configured) ---\n";
    echo "  Note: Set 'project_id' in config to test API key management\n";
    echo "\n";
}

$client->close();

echo "=== All tests completed! ===\n";
echo "\n";
echo "NOTE: Some tests may fail if:\n";
echo "  1. service_auth is not configured in auth-service config.json\n";
echo "  2. Methods require authentication but no valid token is available\n";
echo "  3. Rate limits are exceeded\n";
echo "\n";

