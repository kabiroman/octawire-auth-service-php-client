<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Tests;

use Kabiroman\Octawire\AuthService\Client\AuthClient;
use Kabiroman\Octawire\AuthService\Client\Config;
use PHPUnit\Framework\TestCase;

class AuthClientTest extends TestCase
{
    public function testClientCreation(): void
    {
        $config = new Config([
            'address' => 'localhost:50051',
        ]);

        // Пока клиент не может быть создан без proto классов и gRPC extension
        // Этот тест будет работать после генерации proto и установки gRPC extension
        if (!extension_loaded('grpc')) {
            $this->markTestSkipped('gRPC extension is not loaded');
        }
        
        $this->expectException(\RuntimeException::class);
        $client = new AuthClient($config);
    }

    public function testConfigValidation(): void
    {
        $config = Config::default('localhost:50051');
        $this->assertEquals('localhost:50051', $config->address);
    }
}

