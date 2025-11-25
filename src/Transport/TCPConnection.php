<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Transport;

use Kabiroman\Octawire\AuthService\Client\Config;
use Kabiroman\Octawire\AuthService\Client\Exception\ConnectionException;

/**
 * TCP connection manager for JATP protocol
 */
class TCPConnection
{
    private ?\resource $socket = null;
    private Config $config;
    private bool $connected = false;
    private string $host;
    private int $port;

    public function __construct(Config $config)
    {
        $this->config = $config;
        
        // Parse address if provided, otherwise use TCP config
        if (isset($config->tcp)) {
            $this->host = $config->tcp->host;
            $this->port = $config->tcp->port;
        } else {
            // Fallback to address parsing
            $address = $config->address;
            if (str_contains($address, ':')) {
                [$host, $port] = explode(':', $address, 2);
                $this->host = $host;
                $this->port = (int)$port;
            } else {
                $this->host = $address;
                $this->port = 50052; // Default JATP port
            }
        }
    }

    /**
     * Establish TCP connection with optional TLS
     *
     * @throws ConnectionException
     */
    public function connect(): void
    {
        if ($this->connected && $this->socket !== null) {
            return; // Already connected
        }

        $address = sprintf('tcp://%s:%d', $this->host, $this->port);
        $context = null;

        // Create TLS context if enabled
        $tcpConfig = $this->config->tcp;
        if ($tcpConfig !== null && $tcpConfig->tls !== null && $tcpConfig->tls->enabled) {
            $context = $this->createTLSContext($tcpConfig->tls);
        }

        $connectTimeout = $this->config->timeout?->connect ?? 10.0;
        
        // Set connection timeout
        $errno = 0;
        $errstr = '';
        
        $socket = @stream_socket_client(
            $address,
            $errno,
            $errstr,
            $connectTimeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if ($socket === false) {
            throw new ConnectionException(
                sprintf(
                    "Failed to connect to %s:%d: %s (errno: %d)",
                    $this->host,
                    $this->port,
                    $errstr,
                    $errno
                ),
                $errno
            );
        }

        $this->socket = $socket;
        $this->connected = true;

        // Set read/write timeouts
        $readTimeout = $this->config->timeout?->request ?? 30.0;
        $this->setTimeout($readTimeout);
    }

    /**
     * Disconnect from server
     */
    public function disconnect(): void
    {
        if ($this->socket !== null) {
            @fclose($this->socket);
            $this->socket = null;
            $this->connected = false;
        }
    }

    /**
     * Check if connected
     */
    public function isConnected(): bool
    {
        return $this->connected && $this->socket !== null && !feof($this->socket);
    }

    /**
     * Read a line (newline-delimited JSON)
     *
     * @return string|null Line content or null on EOF
     * @throws ConnectionException
     */
    public function readLine(): ?string
    {
        if (!$this->isConnected()) {
            throw new ConnectionException("Not connected to server");
        }

        $line = @fgets($this->socket);
        
        if ($line === false) {
            if (feof($this->socket)) {
                $this->connected = false;
                return null; // EOF
            }
            
            // Check for timeout
            $meta = stream_get_meta_data($this->socket);
            if ($meta['timed_out']) {
                throw new ConnectionException("Read timeout");
            }
            
            throw new ConnectionException("Failed to read from socket");
        }

        // Remove trailing newline
        return rtrim($line, "\r\n");
    }

    /**
     * Write a line (newline-delimited JSON)
     *
     * @param string $data Data to write
     * @throws ConnectionException
     */
    public function writeLine(string $data): void
    {
        if (!$this->isConnected()) {
            throw new ConnectionException("Not connected to server");
        }

        $dataWithNewline = $data . "\n";
        $written = @fwrite($this->socket, $dataWithNewline);

        if ($written === false || $written !== strlen($dataWithNewline)) {
            // Check for timeout
            $meta = stream_get_meta_data($this->socket);
            if ($meta['timed_out']) {
                throw new ConnectionException("Write timeout");
            }
            
            throw new ConnectionException("Failed to write to socket");
        }
    }

    /**
     * Set read/write timeout
     *
     * @param float $timeout Timeout in seconds
     */
    public function setTimeout(float $timeout): void
    {
        if ($this->socket !== null) {
            $seconds = (int)$timeout;
            $microseconds = (int)(($timeout - $seconds) * 1000000);
            stream_set_timeout($this->socket, $seconds, $microseconds);
        }
    }

    /**
     * Create TLS stream context
     *
     * @param \Kabiroman\Octawire\AuthService\Client\TLSConfig $tlsConfig TLS configuration
     * @return resource Stream context
     */
    private function createTLSContext($tlsConfig)
    {
        $options = [
            'ssl' => [
                'verify_peer' => $tlsConfig->caFile !== null,
                'verify_peer_name' => $tlsConfig->serverName !== null,
                'allow_self_signed' => false,
            ],
        ];

        // CA file for server verification
        if ($tlsConfig->caFile !== null) {
            $options['ssl']['cafile'] = $tlsConfig->caFile;
        }

        // Client certificate for mTLS
        if ($tlsConfig->certFile !== null) {
            $options['ssl']['local_cert'] = $tlsConfig->certFile;
        }

        if ($tlsConfig->keyFile !== null) {
            $options['ssl']['local_pk'] = $tlsConfig->keyFile;
        }

        // Server name for SNI
        if ($tlsConfig->serverName !== null) {
            $options['ssl']['peer_name'] = $tlsConfig->serverName;
        }

        return stream_context_create($options);
    }

    /**
     * Get connection resource (for testing/debugging)
     *
     * @return resource|null
     */
    public function getSocket()
    {
        return $this->socket;
    }

    /**
     * Destructor - ensure connection is closed
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}

