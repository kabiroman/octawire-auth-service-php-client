<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client;

/**
 * Конфигурация клиента Auth Service
 */
class Config
{
    /**
     * Адрес сервера (host:port) - для обратной совместимости
     */
    public readonly string $address;

    /**
     * Транспорт: 'tcp' или 'grpc' (по умолчанию 'tcp')
     */
    public readonly string $transport;

    /**
     * API ключ для аутентификации (опционально)
     */
    public readonly ?string $apiKey;

    /**
     * Дефолтный project_id
     */
    public readonly ?string $projectId;

    /**
     * Настройки TLS (legacy, для обратной совместимости)
     */
    public readonly ?TLSConfig $tls;

    /**
     * Настройки TCP
     */
    public readonly ?TCPConfig $tcp;

    /**
     * Настройки retry
     */
    public readonly ?RetryConfig $retry;

    /**
     * Настройки кэша ключей
     */
    public readonly ?KeyCacheConfig $keyCache;

    /**
     * Настройки таймаутов
     */
    public readonly ?TimeoutConfig $timeout;

    /**
     * Настройки Redis (если используется для кэша)
     */
    public readonly ?RedisConfig $redis;

    public function __construct(array $config = [])
    {
        $this->transport = $config['transport'] ?? 'tcp';
        $this->address = $config['address'] ?? ($this->transport === 'tcp' ? 'localhost:50052' : 'localhost:50051');
        $this->apiKey = $config['api_key'] ?? null;
        $this->projectId = $config['project_id'] ?? null;

        // TLS конфигурация (legacy, для обратной совместимости)
        if (isset($config['tls']) && is_array($config['tls'])) {
            $this->tls = new TLSConfig($config['tls']);
        } else {
            $this->tls = null;
        }

        // TCP конфигурация
        if (isset($config['tcp']) && is_array($config['tcp'])) {
            $this->tcp = new TCPConfig($config['tcp'], $this->tls);
        } else {
            // Auto-create TCP config from address if transport is TCP
            if ($this->transport === 'tcp') {
                $this->tcp = TCPConfig::fromAddress($this->address, $this->tls);
            } else {
                $this->tcp = null;
            }
        }

        // Retry конфигурация
        if (isset($config['retry']) && is_array($config['retry'])) {
            $this->retry = new RetryConfig($config['retry']);
        } else {
            $this->retry = new RetryConfig();
        }

        // KeyCache конфигурация
        if (isset($config['key_cache']) && is_array($config['key_cache'])) {
            $this->keyCache = new KeyCacheConfig($config['key_cache']);
        } else {
            $this->keyCache = new KeyCacheConfig();
        }

        // Timeout конфигурация
        if (isset($config['timeout']) && is_array($config['timeout'])) {
            $this->timeout = new TimeoutConfig($config['timeout']);
        } else {
            $this->timeout = new TimeoutConfig();
        }

        // Redis конфигурация
        if (isset($config['redis']) && is_array($config['redis'])) {
            $this->redis = new RedisConfig($config['redis']);
        } else {
            $this->redis = null;
        }
    }

    /**
     * Создание конфигурации по умолчанию
     */
    public static function default(string $address): self
    {
        return new self(['address' => $address]);
    }
}

/**
 * Конфигурация TLS
 */
class TLSConfig
{
    public readonly bool $enabled;
    public readonly ?string $certFile;
    public readonly ?string $keyFile;
    public readonly ?string $caFile;
    public readonly ?string $serverName;

    public function __construct(array $config = [])
    {
        $this->enabled = $config['enabled'] ?? false;
        $this->certFile = $config['cert_file'] ?? null;
        $this->keyFile = $config['key_file'] ?? null;
        $this->caFile = $config['ca_file'] ?? null;
        $this->serverName = $config['server_name'] ?? null;
    }
}

/**
 * Конфигурация retry
 */
class RetryConfig
{
    public readonly int $maxAttempts;
    public readonly float $initialBackoff;
    public readonly float $maxBackoff;

    public function __construct(array $config = [])
    {
        $this->maxAttempts = $config['max_attempts'] ?? 3;
        $this->initialBackoff = $config['initial_backoff'] ?? 0.1;
        $this->maxBackoff = $config['max_backoff'] ?? 5.0;
    }
}

/**
 * Конфигурация кэша ключей
 */
class KeyCacheConfig
{
    public readonly int $ttl;
    public readonly int $maxSize;
    public readonly string $driver;

    public function __construct(array $config = [])
    {
        $this->ttl = $config['ttl'] ?? 3600;
        $this->maxSize = $config['max_size'] ?? 100;
        $this->driver = $config['driver'] ?? 'memory';
    }
}

/**
 * Конфигурация таймаутов
 */
class TimeoutConfig
{
    public readonly float $connect;
    public readonly float $request;

    public function __construct(array $config = [])
    {
        $this->connect = $config['connect'] ?? 10.0;
        $this->request = $config['request'] ?? 30.0;
    }
}

/**
 * Конфигурация Redis
 */
class RedisConfig
{
    public readonly string $host;
    public readonly int $port;
    public readonly int $db;
    public readonly ?string $password;

    public function __construct(array $config = [])
    {
        $this->host = $config['host'] ?? 'localhost';
        $this->port = $config['port'] ?? 6379;
        $this->db = $config['db'] ?? 0;
        $this->password = $config['password'] ?? null;
    }
}

/**
 * Конфигурация TCP
 */
class TCPConfig
{
    public readonly string $host;
    public readonly int $port;
    public readonly ?TLSConfig $tls;
    public readonly bool $persistent;

    public function __construct(array $config = [], ?TLSConfig $legacyTls = null)
    {
        // Parse host:port from address if provided
        if (isset($config['address'])) {
            $address = $config['address'];
            if (str_contains($address, ':')) {
                [$host, $port] = explode(':', $address, 2);
                $this->host = $host;
                $this->port = (int)$port;
            } else {
                $this->host = $address;
                $this->port = $config['port'] ?? 50052;
            }
        } else {
            $this->host = $config['host'] ?? 'localhost';
            $this->port = $config['port'] ?? 50052;
        }

        // TLS configuration (from tcp.tls or legacy tls)
        if (isset($config['tls']) && is_array($config['tls'])) {
            $this->tls = new TLSConfig($config['tls']);
        } elseif ($legacyTls !== null) {
            $this->tls = $legacyTls;
        } else {
            $this->tls = null;
        }

        $this->persistent = $config['persistent'] ?? true;
    }

    /**
     * Create TCPConfig from address string
     */
    public static function fromAddress(string $address, ?TLSConfig $tls = null): self
    {
        if (str_contains($address, ':')) {
            [$host, $port] = explode(':', $address, 2);
            return new self(['host' => $host, 'port' => (int)$port], $tls);
        }
        return new self(['host' => $address, 'port' => 50052], $tls);
    }
}

