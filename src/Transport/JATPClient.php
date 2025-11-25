<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Transport;

use Kabiroman\Octawire\AuthService\Client\Config;
use Kabiroman\Octawire\AuthService\Client\Exception\ConnectionException;
use JsonException;

/**
 * High-level JATP client combining TCPConnection and JATPProtocol
 */
class JATPClient
{
    private TCPConnection $connection;
    private JATPProtocol $protocol;
    private Config $config;
    private bool $persistent;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->connection = new TCPConnection($config);
        $this->protocol = new JATPProtocol();
        $this->persistent = $config->tcp?->persistent ?? true;
    }

    /**
     * Execute JATP request
     *
     * @param string $method Service and method name (e.g., "JWTService.IssueToken")
     * @param array $payload Request payload
     * @param string|null $jwtToken JWT token for authentication
     * @param string|null $serviceName Service name for inter-service auth
     * @param string|null $serviceSecret Service secret for inter-service auth
     * @return array Response data
     * @throws ConnectionException
     * @throws JsonException
     */
    public function call(
        string $method,
        array $payload,
        ?string $jwtToken = null,
        ?string $serviceName = null,
        ?string $serviceSecret = null
    ): array {
        // Ensure connection
        if (!$this->connection->isConnected()) {
            $this->connection->connect();
        }

        // Build request
        $requestJson = $this->protocol->buildRequest(
            $method,
            $payload,
            $jwtToken,
            $serviceName,
            $serviceSecret
        );

        // Send request
        try {
            $this->connection->writeLine($requestJson);
        } catch (ConnectionException $e) {
            // Reconnect and retry once if persistent connection
            if ($this->persistent) {
                $this->connection->disconnect();
                $this->connection->connect();
                $this->connection->writeLine($requestJson);
            } else {
                throw $e;
            }
        }

        // Read response
        $responseLine = $this->connection->readLine();
        if ($responseLine === null) {
            throw new ConnectionException("Connection closed by server");
        }

        // Parse response
        $response = $this->protocol->parseResponse($responseLine);

        // Handle errors
        if (!$response['success']) {
            $error = $response['error'];
            $code = $error['code'] ?? 'ERROR_UNKNOWN';
            $message = $error['message'] ?? 'Unknown error';
            
            // Create exception with JATP error format that ErrorHandler can parse
            // Format: [ERROR_CODE] message
            throw new \RuntimeException(
                sprintf("[%s] %s", $code, $message),
                0,
                null
            );
        }

        // Return data
        return $response['data'] ?? [];
    }

    /**
     * Close connection
     */
    public function close(): void
    {
        if (!$this->persistent) {
            $this->connection->disconnect();
        }
    }

    /**
     * Force disconnect (even for persistent connections)
     */
    public function disconnect(): void
    {
        $this->connection->disconnect();
    }

    /**
     * Check if connected
     */
    public function isConnected(): bool
    {
        return $this->connection->isConnected();
    }

}

