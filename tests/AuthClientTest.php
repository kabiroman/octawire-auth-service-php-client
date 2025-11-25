<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Tests;

use Kabiroman\Octawire\AuthService\Client\AuthClient;
use Kabiroman\Octawire\AuthService\Client\Config;
use Kabiroman\Octawire\AuthService\Client\Exception\ConnectionException;
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
}

