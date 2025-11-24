<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Tests;

use Kabiroman\Octawire\AuthService\Client\Config;
use Kabiroman\Octawire\AuthService\Client\KeyCache;
use Kabiroman\Octawire\AuthService\Client\Model\PublicKeyInfo;
use PHPUnit\Framework\TestCase;

class KeyCacheTest extends TestCase
{
    private KeyCache $cache;

    protected function setUp(): void
    {
        $this->cache = new KeyCache();
    }

    public function testGetSet(): void
    {
        $projectId = 'project-1';
        $keyId = 'key-1';
        $keyInfo = new PublicKeyInfo(
            keyId: $keyId,
            publicKeyPem: 'public-key-pem',
            isPrimary: true,
            expiresAt: time() + 3600
        );

        $this->cache->set($projectId, $keyInfo, time() + 1800);
        $cached = $this->cache->get($projectId, $keyId);

        $this->assertNotNull($cached);
        $this->assertEquals($keyId, $cached->keyId);
        $this->assertEquals('public-key-pem', $cached->publicKeyPem);
    }

    public function testGetNotFound(): void
    {
        $result = $this->cache->get('non-existent', 'key-1');
        $this->assertNull($result);
    }

    public function testGetAllActive(): void
    {
        $projectId = 'project-1';
        $key1 = new PublicKeyInfo('key-1', 'pem-1', true, time() + 3600);
        $key2 = new PublicKeyInfo('key-2', 'pem-2', false, time() + 3600);

        $this->cache->set($projectId, $key1, time() + 1800);
        $this->cache->set($projectId, $key2, time() + 1800);

        $active = $this->cache->getAllActive($projectId);
        $this->assertCount(2, $active);
    }

    public function testInvalidate(): void
    {
        $projectId = 'project-1';
        $keyInfo = new PublicKeyInfo('key-1', 'pem', true, time() + 3600);

        $this->cache->set($projectId, $keyInfo, time() + 1800);
        $this->cache->invalidate($projectId);

        $result = $this->cache->get($projectId, 'key-1');
        $this->assertNull($result);
    }

    public function testClear(): void
    {
        $keyInfo = new PublicKeyInfo('key-1', 'pem', true, time() + 3600);
        $this->cache->set('project-1', $keyInfo, time() + 1800);
        $this->cache->clear();

        $result = $this->cache->get('project-1', 'key-1');
        $this->assertNull($result);
    }

    public function testExpiredKey(): void
    {
        $projectId = 'project-1';
        $keyInfo = new PublicKeyInfo('key-1', 'pem', true, time() - 100); // Истекший ключ

        $this->cache->set($projectId, $keyInfo, time() + 1800);
        $result = $this->cache->get($projectId, 'key-1');

        $this->assertNull($result); // Истекший ключ не должен возвращаться
    }
}

