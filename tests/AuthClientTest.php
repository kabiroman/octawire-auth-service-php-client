<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Tests;

use Kabiroman\Octawire\AuthService\Client\AuthClient;
use Kabiroman\Octawire\AuthService\Client\Config;
use Kabiroman\Octawire\AuthService\Client\Exception\AuthException;
use Kabiroman\Octawire\AuthService\Client\Exception\ConnectionException;
use Kabiroman\Octawire\AuthService\Client\Request\JWT\IssueServiceTokenRequest;
use PHPUnit\Framework\TestCase;

class AuthClientTest extends TestCase
{
    public function testClientCreationWithInvalidConfig(): void
    {
        // Конфигурация без TCP настроек - должна вызвать исключение
        $config = new Config([
            'transport' => 'tcp',
            'tcp' => [
                'host' => 'invalid-host-that-does-not-exist',
                'port' => 9999,
                'tls' => ['enabled' => false],
            ],
        ]);

        // Клиент должен создать, но соединение должно упасть при первом использовании
        // Просто проверяем, что клиент создается без ошибок
        $this->expectException(ConnectionException::class);
        $client = new AuthClient($config);
        // Попытка выполнить запрос должна привести к ConnectionException
        $client->healthCheck();
    }

    public function testConfigValidation(): void
    {
        $config = Config::default('localhost:50052');
        $this->assertEquals('localhost:50052', $config->address);
        $this->assertEquals('tcp', $config->transport);
    }

    public function testConfigWithTCP(): void
    {
        $config = new Config([
            'transport' => 'tcp',
            'tcp' => [
                'host' => 'localhost',
                'port' => 50052,
                'tls' => ['enabled' => false],
            ],
        ]);

        $this->assertEquals('tcp', $config->transport);
        $this->assertNotNull($config->tcp);
        $this->assertEquals('localhost', $config->tcp->host);
        $this->assertEquals(50052, $config->tcp->port);
    }

    public function testConfigWithServiceSecret(): void
    {
        $config = new Config([
            'transport' => 'tcp',
            'tcp' => [
                'host' => 'localhost',
                'port' => 50052,
                'tls' => ['enabled' => false],
            ],
            'service_secret' => 'test-service-secret-abc123',
        ]);

        $this->assertEquals('test-service-secret-abc123', $config->serviceSecret);
    }

    public function testConfigWithoutServiceSecret(): void
    {
        $config = new Config([
            'transport' => 'tcp',
            'tcp' => [
                'host' => 'localhost',
                'port' => 50052,
                'tls' => ['enabled' => false],
            ],
        ]);

        $this->assertNull($config->serviceSecret);
    }

    public function testIssueServiceTokenValidationEmptySourceService(): void
    {
        $config = new Config([
            'transport' => 'tcp',
            'tcp' => [
                'host' => 'localhost',
                'port' => 50052,
                'tls' => ['enabled' => false],
            ],
        ]);

        $client = new AuthClient($config);

        $request = new IssueServiceTokenRequest(
            sourceService: '', // Пустой sourceService
            ttl: 3600,
        );

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('sourceService is required');
        $client->issueServiceToken($request);
    }

    public function testIssueServiceTokenValidationMissingServiceSecret(): void
    {
        $config = new Config([
            'transport' => 'tcp',
            'tcp' => [
                'host' => 'localhost',
                'port' => 50052,
                'tls' => ['enabled' => false],
            ],
            // service_secret не указан
        ]);

        $client = new AuthClient($config);

        $request = new IssueServiceTokenRequest(
            sourceService: 'identity-service',
            ttl: 3600,
        );

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('serviceSecret is required');
        $client->issueServiceToken($request);
    }

    public function testIssueServiceTokenWithServiceSecretFromConfig(): void
    {
        $config = new Config([
            'transport' => 'tcp',
            'tcp' => [
                'host' => 'localhost',
                'port' => 50052,
                'tls' => ['enabled' => false],
            ],
            'service_secret' => 'test-service-secret-from-config',
        ]);

        $client = new AuthClient($config);

        $request = new IssueServiceTokenRequest(
            sourceService: 'identity-service',
            ttl: 3600,
        );

        // Должно использовать service_secret из конфига
        // Но так как сервер недоступен, получим ConnectionException
        $this->expectException(ConnectionException::class);
        $client->issueServiceToken($request);
    }

    public function testIssueServiceTokenWithServiceSecretAsParameter(): void
    {
        $config = new Config([
            'transport' => 'tcp',
            'tcp' => [
                'host' => 'localhost',
                'port' => 50052,
                'tls' => ['enabled' => false],
            ],
            'service_secret' => 'config-secret',
        ]);

        $client = new AuthClient($config);

        $request = new IssueServiceTokenRequest(
            sourceService: 'identity-service',
            ttl: 3600,
        );

        // Переданный параметр должен иметь приоритет над конфигом
        // Но так как сервер недоступен, получим ConnectionException
        $this->expectException(ConnectionException::class);
        $client->issueServiceToken($request, 'parameter-secret');
    }
}

