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
            projectId: 'test-project-id', // Обязательное поле (v0.9.3+)
            ttl: 3600,
        );

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('sourceService is required');
        $client->issueServiceToken($request);
    }

    public function testIssueServiceTokenWithoutServiceSecret(): void
    {
        // Test that IssueServiceToken can be called without serviceSecret (optional in v1.0+)
        $config = new Config([
            'transport' => 'tcp',
            'tcp' => [
                'host' => 'localhost',
                'port' => 50052,
                'tls' => ['enabled' => false],
            ],
            // service_secret не указан - опциональный (v1.0+)
        ]);

        $client = new AuthClient($config);

        $request = new IssueServiceTokenRequest(
            sourceService: 'identity-service',
            projectId: 'test-project-id', // Обязательное поле (v0.9.3+)
            ttl: 3600,
        );

        // Должно работать без serviceSecret (будет вызвано без service auth)
        // Но так как сервер недоступен, получим ConnectionException
        $this->expectException(ConnectionException::class);
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
            projectId: 'test-project-id', // Обязательное поле (v0.9.3+)
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
            projectId: 'test-project-id', // Обязательное поле (v0.9.3+)
            ttl: 3600,
        );

        // Переданный параметр должен иметь приоритет над конфигом
        // Но так как сервер недоступен, получим ConnectionException
        $this->expectException(ConnectionException::class);
        $client->issueServiceToken($request, 'parameter-secret');
    }
    
    public function testValidateTokenWithoutJWT(): void
    {
        // Test that ValidateToken can be called without JWT token (optional service auth in v1.0+)
        $config = new Config([
            'transport' => 'tcp',
            'tcp' => [
                'host' => 'localhost',
                'port' => 50052,
                'tls' => ['enabled' => false],
            ],
            // api_key не указан - не используется для ValidateToken в v1.0+
        ]);

        $client = new AuthClient($config);

        $request = new \Kabiroman\Octawire\AuthService\Client\Request\JWT\ValidateTokenRequest(
            token: 'test-token',
            projectId: 'test-project-id',
        );

        // Должно работать без JWT токена (будет вызвано как публичный метод или с опциональной service auth)
        // Но так как сервер недоступен, получим ConnectionException
        $this->expectException(ConnectionException::class);
        $client->validateToken($request);
    }
    
    public function testParseTokenWithoutJWT(): void
    {
        // Test that ParseToken can be called without JWT token (optional service auth in v1.0+)
        $config = new Config([
            'transport' => 'tcp',
            'tcp' => [
                'host' => 'localhost',
                'port' => 50052,
                'tls' => ['enabled' => false],
            ],
        ]);

        $client = new AuthClient($config);

        $request = new \Kabiroman\Octawire\AuthService\Client\Request\JWT\ParseTokenRequest(
            token: 'test-token',
            projectId: 'test-project-id',
        );

        // Должно работать без JWT токена (v1.0+)
        $this->expectException(ConnectionException::class);
        $client->parseToken($request);
    }
    
    public function testExtractClaimsWithoutJWT(): void
    {
        // Test that ExtractClaims can be called without JWT token (optional service auth in v1.0+)
        $config = new Config([
            'transport' => 'tcp',
            'tcp' => [
                'host' => 'localhost',
                'port' => 50052,
                'tls' => ['enabled' => false],
            ],
        ]);

        $client = new AuthClient($config);

        $request = new \Kabiroman\Octawire\AuthService\Client\Request\JWT\ExtractClaimsRequest(
            token: 'test-token',
            projectId: 'test-project-id',
            claimKeys: ['user_id'],
        );

        // Должно работать без JWT токена (v1.0+)
        $this->expectException(ConnectionException::class);
        $client->extractClaims($request);
    }
    
    public function testValidateBatchWithoutJWT(): void
    {
        // Test that ValidateBatch can be called without JWT token (optional service auth in v1.0+)
        $config = new Config([
            'transport' => 'tcp',
            'tcp' => [
                'host' => 'localhost',
                'port' => 50052,
                'tls' => ['enabled' => false],
            ],
        ]);

        $client = new AuthClient($config);

        $request = new \Kabiroman\Octawire\AuthService\Client\Request\JWT\ValidateBatchRequest(
            tokens: ['test-token-1', 'test-token-2'],
            checkBlacklist: true,
        );

        // Должно работать без JWT токена (v1.0+)
        $this->expectException(ConnectionException::class);
        $client->validateBatch($request);
    }
}

