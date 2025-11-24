<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client;

/**
 * Конфигурация клиента Auth Service
 */
class Config
{
    /**
     * Адрес сервера (host:port)
     */
    public readonly string $address;

    /**
     * API ключ для аутентификации (опционально)
     */
    public readonly ?string $apiKey;

    /**
     * Дефолтный project_id
     */
    public readonly ?string $projectId;

    /**
     * Настройки TLS
     */
    public readonly ?TLSConfig $tls;

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
        $this->address = $config['address'] ?? 'localhost:50051';
        $this->apiKey = $config['api_key'] ?? null;
        $this->projectId = $config['project_id'] ?? null;

        // TLS конфигурация
        if (isset($config['tls']) && is_array($config['tls'])) {
            $this->tls = new TLSConfig($config['tls']);
        } else {
            $this->tls = null;
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

