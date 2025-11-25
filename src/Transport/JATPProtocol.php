<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Transport;

use JsonException;

/**
 * JATP protocol handler for request/response serialization
 */
class JATPProtocol
{
    private const PROTOCOL_VERSION = '1.0';

    /**
     * Build JATP request JSON
     *
     * @param string $method Service and method name (e.g., "JWTService.IssueToken")
     * @param mixed $payload Request payload (proto message fields as array or object)
     * @param string|null $jwtToken JWT token for authentication
     * @param string|null $serviceName Service name for inter-service auth
     * @param string|null $serviceSecret Service secret for inter-service auth
     * @param string|null $requestId Request ID (UUID v7, auto-generated if null)
     * @return string JSON-encoded request
     * @throws JsonException
     */
    public function buildRequest(
        string $method,
        mixed $payload,
        ?string $jwtToken = null,
        ?string $serviceName = null,
        ?string $serviceSecret = null,
        ?string $requestId = null
    ): string {
        // Convert empty array to empty object for protobuf compatibility
        if (is_array($payload) && empty($payload)) {
            $payload = new \stdClass();
        }
        
        $request = [
            'protocol_version' => self::PROTOCOL_VERSION,
            'method' => $method,
            'request_id' => $requestId ?? $this->generateRequestId(),
            'payload' => $payload,
        ];

        // Add authentication
        if ($jwtToken !== null) {
            $request['jwt_token'] = $jwtToken;
        } elseif ($serviceName !== null && $serviceSecret !== null) {
            $request['service_name'] = $serviceName;
            $request['service_secret'] = $serviceSecret;
        }

        return json_encode($request, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * Parse JATP response JSON
     *
     * @param string $json JSON-encoded response
     * @return array Parsed response with 'success', 'data', 'error' keys
     * @throws JsonException
     */
    public function parseResponse(string $json): array
    {
        $response = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (!isset($response['protocol_version'])) {
            throw new \InvalidArgumentException('Missing protocol_version in response');
        }

        if (!isset($response['request_id'])) {
            throw new \InvalidArgumentException('Missing request_id in response');
        }

        if (!isset($response['success'])) {
            throw new \InvalidArgumentException('Missing success field in response');
        }

        return [
            'protocol_version' => $response['protocol_version'],
            'request_id' => $response['request_id'],
            'success' => (bool)$response['success'],
            'data' => $response['data'] ?? null,
            'error' => $response['error'] ?? null,
        ];
    }

    /**
     * Generate UUID v7 for request ID
     *
     * UUID v7 format: timestamp (48 bits) + version (4 bits) + variant (2 bits) + random (62 bits)
     * Since PHP doesn't have native UUID v7, we'll use a simplified version
     * that combines timestamp with random data for uniqueness.
     *
     * @return string UUID-like string (format: 018f1234-5678-7890-abcd-ef1234567890)
     */
    public function generateRequestId(): string
    {
        // Get current timestamp in milliseconds
        $timestamp = (int)(microtime(true) * 1000);
        
        // Convert to hex (48 bits = 12 hex chars)
        $timestampHex = dechex($timestamp);
        $timestampHex = str_pad($timestampHex, 12, '0', STR_PAD_LEFT);
        
        // Generate random bytes (62 bits = 15.5 hex chars, we'll use 16)
        $random = bin2hex(random_bytes(8)); // 16 hex chars
        
        // Format as UUID: timestamp (12) + version (1) + random (15) = 28 hex chars
        // Format: xxxxxxxx-xxxx-7xxx-xxxx-xxxxxxxxxxxx
        $uuid = sprintf(
            '%s-%s-7%s-%s-%s',
            substr($timestampHex, 0, 8),
            substr($timestampHex, 8, 4),
            substr($random, 0, 3),
            substr($random, 3, 4),
            substr($random, 7, 12)
        );

        return $uuid;
    }

    /**
     * Validate protocol version
     *
     * @param string $version Protocol version to validate
     * @return bool True if version is supported
     */
    public function validateProtocolVersion(string $version): bool
    {
        return $version === self::PROTOCOL_VERSION;
    }

    /**
     * Get supported protocol version
     *
     * @return string Protocol version
     */
    public function getProtocolVersion(): string
    {
        return self::PROTOCOL_VERSION;
    }
}

