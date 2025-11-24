<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client;

use Grpc\ChannelCredentials;
use Grpc\ChannelCredentialsInterface;
use Kabiroman\Octawire\AuthService\Client\Exception\ConnectionException;

/**
 * Утилиты для работы с TLS/mTLS конфигурацией
 */
final class TLSConfigHelper
{
    /**
     * Создание gRPC credentials из конфигурации TLS
     */
    public static function createCredentials(?TLSConfig $tlsConfig): ChannelCredentialsInterface
    {
        if ($tlsConfig === null || !$tlsConfig->enabled) {
            return ChannelCredentials::createInsecure();
        }

        $options = [];

        // Загрузка CA сертификата
        if ($tlsConfig->caFile !== null) {
            if (!file_exists($tlsConfig->caFile)) {
                throw new ConnectionException("CA certificate file not found: {$tlsConfig->caFile}");
            }
            $caCert = file_get_contents($tlsConfig->caFile);
            if ($caCert === false) {
                throw new ConnectionException("Failed to read CA certificate file: {$tlsConfig->caFile}");
            }
            $options['grpc.ssl_target_name_override'] = $tlsConfig->serverName ?? '';
        }

        // Загрузка клиентского сертификата и ключа (для mTLS)
        if ($tlsConfig->certFile !== null && $tlsConfig->keyFile !== null) {
            if (!file_exists($tlsConfig->certFile)) {
                throw new ConnectionException("Client certificate file not found: {$tlsConfig->certFile}");
            }
            if (!file_exists($tlsConfig->keyFile)) {
                throw new ConnectionException("Client key file not found: {$tlsConfig->keyFile}");
            }

            $clientCert = file_get_contents($tlsConfig->certFile);
            $clientKey = file_get_contents($tlsConfig->keyFile);

            if ($clientCert === false) {
                throw new ConnectionException("Failed to read client certificate file: {$tlsConfig->certFile}");
            }
            if ($clientKey === false) {
                throw new ConnectionException("Failed to read client key file: {$tlsConfig->keyFile}");
            }

            // Для mTLS нужно передать сертификат и ключ
            // gRPC PHP extension использует другой подход
            $options['credentials'] = [
                'cert' => $clientCert,
                'key' => $clientKey,
            ];
        }

        // Server Name Indication (SNI)
        if ($tlsConfig->serverName !== null) {
            $options['grpc.ssl_target_name_override'] = $tlsConfig->serverName;
        }

        // Создание credentials
        if (!empty($options)) {
            // Для PHP gRPC extension используется другой API
            // В зависимости от версии extension может отличаться
            try {
                return ChannelCredentials::createSsl($tlsConfig->caFile);
            } catch (\Exception $e) {
                throw new ConnectionException("Failed to create TLS credentials: " . $e->getMessage());
            }
        }

        return ChannelCredentials::createSsl();
    }

    /**
     * Валидация конфигурации TLS
     */
    public static function validate(?TLSConfig $tlsConfig): void
    {
        if ($tlsConfig === null || !$tlsConfig->enabled) {
            return;
        }

        // Проверка наличия файлов
        if ($tlsConfig->caFile !== null && !file_exists($tlsConfig->caFile)) {
            throw new ConnectionException("CA certificate file not found: {$tlsConfig->caFile}");
        }

        if ($tlsConfig->certFile !== null && !file_exists($tlsConfig->certFile)) {
            throw new ConnectionException("Client certificate file not found: {$tlsConfig->certFile}");
        }

        if ($tlsConfig->keyFile !== null && !file_exists($tlsConfig->keyFile)) {
            throw new ConnectionException("Client key file not found: {$tlsConfig->keyFile}");
        }

        // Проверка прав доступа к файлам
        if ($tlsConfig->keyFile !== null && !is_readable($tlsConfig->keyFile)) {
            throw new ConnectionException("Client key file is not readable: {$tlsConfig->keyFile}");
        }
    }
}

