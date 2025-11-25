<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client;

use Kabiroman\Octawire\AuthService\Client\Exception\ConnectionException;

/**
 * Утилиты для работы с TLS/mTLS конфигурацией для TCP streams
 */
final class TLSConfigHelper
{
    /**
     * Создание stream context для TCP с TLS
     *
     * @param TLSConfig|null $tlsConfig Конфигурация TLS
     * @return resource|null Stream context или null если TLS отключен
     */
    public static function createStreamContext(?TLSConfig $tlsConfig)
    {
        if ($tlsConfig === null || !$tlsConfig->enabled) {
            return null;
        }

        // Валидация конфигурации
        self::validate($tlsConfig);

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
     * Валидация конфигурации TLS
     *
     * @param TLSConfig|null $tlsConfig Конфигурация TLS
     * @throws ConnectionException
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

        // Проверка согласованности mTLS
        if (($tlsConfig->certFile !== null && $tlsConfig->keyFile === null) ||
            ($tlsConfig->certFile === null && $tlsConfig->keyFile !== null)) {
            throw new ConnectionException("Both cert_file and key_file must be provided for mTLS");
        }
    }
}
