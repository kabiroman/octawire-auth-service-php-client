<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Tests;

use Kabiroman\Octawire\AuthService\Client\Request\JWT\ExtractClaimsRequest;
use Kabiroman\Octawire\AuthService\Client\Request\JWT\IssueServiceTokenRequest;
use Kabiroman\Octawire\AuthService\Client\Request\JWT\IssueTokenRequest;
use Kabiroman\Octawire\AuthService\Client\Request\JWT\ParseTokenRequest;
use Kabiroman\Octawire\AuthService\Client\Request\JWT\RefreshTokenRequest;
use Kabiroman\Octawire\AuthService\Client\Request\JWT\RevokeTokenRequest;
use Kabiroman\Octawire\AuthService\Client\Request\JWT\ValidateTokenRequest;
use PHPUnit\Framework\TestCase;

class RequestTest extends TestCase
{
    public function testIssueTokenRequestWithProjectId(): void
    {
        $request = new IssueTokenRequest(
            userId: 'user-123',
            projectId: 'project-456',
            claims: ['role' => 'admin'],
            accessTokenTtl: 3600,
            refreshTokenTtl: 86400,
        );

        $data = $request->toArray();

        $this->assertEquals('user-123', $data['user_id']);
        $this->assertEquals('project-456', $data['project_id']);
        $this->assertEquals(['role' => 'admin'], $data['claims']);
        $this->assertEquals(3600, $data['access_token_ttl']);
        $this->assertEquals(86400, $data['refresh_token_ttl']);
    }

    public function testIssueTokenRequestWithMinimalFields(): void
    {
        $request = new IssueTokenRequest(
            userId: 'user-123',
            projectId: 'project-456',
        );

        $data = $request->toArray();

        $this->assertEquals('user-123', $data['user_id']);
        $this->assertEquals('project-456', $data['project_id']);
        $this->assertArrayNotHasKey('claims', $data);
        $this->assertArrayNotHasKey('access_token_ttl', $data);
    }

    public function testIssueServiceTokenRequestWithProjectId(): void
    {
        $request = new IssueServiceTokenRequest(
            sourceService: 'identity-service',
            projectId: 'project-456',
            targetService: 'gateway-service',
            userId: 'service-user',
            claims: ['service' => 'identity'],
            ttl: 3600,
        );

        $data = $request->toArray();

        $this->assertEquals('identity-service', $data['source_service']);
        $this->assertEquals('project-456', $data['project_id']);
        $this->assertEquals('gateway-service', $data['target_service']);
        $this->assertEquals('service-user', $data['user_id']);
        $this->assertEquals(['service' => 'identity'], $data['claims']);
        $this->assertEquals(3600, $data['ttl']);
    }

    public function testIssueServiceTokenRequestWithMinimalFields(): void
    {
        $request = new IssueServiceTokenRequest(
            sourceService: 'identity-service',
            projectId: 'project-456',
        );

        $data = $request->toArray();

        $this->assertEquals('identity-service', $data['source_service']);
        $this->assertEquals('project-456', $data['project_id']);
        $this->assertArrayNotHasKey('target_service', $data);
        $this->assertArrayNotHasKey('user_id', $data);
    }

    public function testValidateTokenRequestWithProjectId(): void
    {
        $request = new ValidateTokenRequest(
            token: 'jwt-token-123',
            projectId: 'project-456',
            checkBlacklist: true,
        );

        $data = $request->toArray();

        $this->assertEquals('jwt-token-123', $data['token']);
        $this->assertEquals('project-456', $data['project_id']);
        $this->assertTrue($data['check_blacklist']);
    }

    public function testValidateTokenRequestWithCheckBlacklistFalse(): void
    {
        $request = new ValidateTokenRequest(
            token: 'jwt-token-123',
            projectId: 'project-456',
            checkBlacklist: false,
        );

        $data = $request->toArray();

        $this->assertEquals('jwt-token-123', $data['token']);
        $this->assertEquals('project-456', $data['project_id']);
        $this->assertFalse($data['check_blacklist']);
    }

    public function testRefreshTokenRequestWithProjectId(): void
    {
        $request = new RefreshTokenRequest(
            refreshToken: 'refresh-token-123',
            projectId: 'project-456',
            deviceId: 'device-789',
        );

        $data = $request->toArray();

        $this->assertEquals('refresh-token-123', $data['refresh_token']);
        $this->assertEquals('project-456', $data['project_id']);
        $this->assertEquals('device-789', $data['device_id']);
    }

    public function testRefreshTokenRequestWithoutDeviceId(): void
    {
        $request = new RefreshTokenRequest(
            refreshToken: 'refresh-token-123',
            projectId: 'project-456',
        );

        $data = $request->toArray();

        $this->assertEquals('refresh-token-123', $data['refresh_token']);
        $this->assertEquals('project-456', $data['project_id']);
        $this->assertArrayNotHasKey('device_id', $data);
    }

    public function testParseTokenRequestWithProjectId(): void
    {
        $request = new ParseTokenRequest(
            token: 'jwt-token-123',
            projectId: 'project-456',
        );

        $data = $request->toArray();

        $this->assertEquals('jwt-token-123', $data['token']);
        $this->assertEquals('project-456', $data['project_id']);
    }

    public function testExtractClaimsRequestWithProjectId(): void
    {
        $request = new ExtractClaimsRequest(
            token: 'jwt-token-123',
            projectId: 'project-456',
            claimKeys: ['user_id', 'role'],
        );

        $data = $request->toArray();

        $this->assertEquals('jwt-token-123', $data['token']);
        $this->assertEquals('project-456', $data['project_id']);
        $this->assertEquals(['user_id', 'role'], $data['claim_keys']);
    }

    public function testExtractClaimsRequestWithoutClaimKeys(): void
    {
        $request = new ExtractClaimsRequest(
            token: 'jwt-token-123',
            projectId: 'project-456',
        );

        $data = $request->toArray();

        $this->assertEquals('jwt-token-123', $data['token']);
        $this->assertEquals('project-456', $data['project_id']);
        $this->assertArrayNotHasKey('claim_keys', $data);
    }

    public function testRevokeTokenRequestWithProjectId(): void
    {
        $request = new RevokeTokenRequest(
            token: 'jwt-token-123',
            projectId: 'project-456',
            ttl: 3600,
        );

        $data = $request->toArray();

        $this->assertEquals('jwt-token-123', $data['token']);
        $this->assertEquals('project-456', $data['project_id']);
        $this->assertEquals(3600, $data['ttl']);
    }

    public function testRevokeTokenRequestWithoutTtl(): void
    {
        $request = new RevokeTokenRequest(
            token: 'jwt-token-123',
            projectId: 'project-456',
        );

        $data = $request->toArray();

        $this->assertEquals('jwt-token-123', $data['token']);
        $this->assertEquals('project-456', $data['project_id']);
        $this->assertArrayNotHasKey('ttl', $data);
    }

    public function testAllRequestClassesIncludeProjectId(): void
    {
        $requests = [
            new IssueTokenRequest('user-123', 'project-456'),
            new IssueServiceTokenRequest('service-1', 'project-456'),
            new ValidateTokenRequest('token-123', 'project-456'),
            new RefreshTokenRequest('refresh-123', 'project-456'),
            new ParseTokenRequest('token-123', 'project-456'),
            new ExtractClaimsRequest('token-123', 'project-456'),
            new RevokeTokenRequest('token-123', 'project-456'),
        ];

        foreach ($requests as $request) {
            $data = $request->toArray();
            $this->assertArrayHasKey('project_id', $data, get_class($request) . ' must include project_id');
            $this->assertEquals('project-456', $data['project_id'], get_class($request) . ' must have correct project_id');
        }
    }
}

