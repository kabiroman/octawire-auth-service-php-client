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
use Kabiroman\Octawire\AuthService\Client\Request\JWT\ValidateBatchRequest;
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

        $this->assertEquals('user-123', $data['userId']);
        $this->assertEquals('project-456', $data['projectId']);
        $this->assertEquals(['role' => 'admin'], $data['claims']);
        $this->assertEquals(3600, $data['accessTokenTtl']);
        $this->assertEquals(86400, $data['refreshTokenTtl']);
    }

    public function testIssueTokenRequestWithMinimalFields(): void
    {
        $request = new IssueTokenRequest(
            userId: 'user-123',
            projectId: 'project-456',
        );

        $data = $request->toArray();

        $this->assertEquals('user-123', $data['userId']);
        $this->assertEquals('project-456', $data['projectId']);
        $this->assertArrayNotHasKey('claims', $data);
        $this->assertArrayNotHasKey('accessTokenTtl', $data);
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

        $this->assertEquals('identity-service', $data['sourceService']);
        $this->assertEquals('project-456', $data['projectId']);
        $this->assertEquals('gateway-service', $data['targetService']);
        $this->assertEquals('service-user', $data['userId']);
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

        $this->assertEquals('identity-service', $data['sourceService']);
        $this->assertEquals('project-456', $data['projectId']);
        $this->assertArrayNotHasKey('targetService', $data);
        $this->assertArrayNotHasKey('userId', $data);
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
        $this->assertEquals('project-456', $data['projectId']);
        $this->assertTrue($data['checkBlacklist']);
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
        $this->assertEquals('project-456', $data['projectId']);
        $this->assertFalse($data['checkBlacklist']);
    }

    public function testRefreshTokenRequestWithProjectId(): void
    {
        $request = new RefreshTokenRequest(
            refreshToken: 'refresh-token-123',
            projectId: 'project-456',
            deviceId: 'device-789',
        );

        $data = $request->toArray();

        $this->assertEquals('refresh-token-123', $data['refreshToken']);
        $this->assertEquals('project-456', $data['projectId']);
        $this->assertEquals('device-789', $data['deviceId']);
    }

    public function testRefreshTokenRequestWithoutDeviceId(): void
    {
        $request = new RefreshTokenRequest(
            refreshToken: 'refresh-token-123',
            projectId: 'project-456',
        );

        $data = $request->toArray();

        $this->assertEquals('refresh-token-123', $data['refreshToken']);
        $this->assertEquals('project-456', $data['projectId']);
        $this->assertArrayNotHasKey('deviceId', $data);
    }

    public function testParseTokenRequestWithProjectId(): void
    {
        $request = new ParseTokenRequest(
            token: 'jwt-token-123',
            projectId: 'project-456',
        );

        $data = $request->toArray();

        $this->assertEquals('jwt-token-123', $data['token']);
        $this->assertEquals('project-456', $data['projectId']);
    }

    public function testExtractClaimsRequestWithProjectId(): void
    {
        $request = new ExtractClaimsRequest(
            token: 'jwt-token-123',
            projectId: 'project-456',
            claimKeys: ['userId', 'role'],
        );

        $data = $request->toArray();

        $this->assertEquals('jwt-token-123', $data['token']);
        $this->assertEquals('project-456', $data['projectId']);
        $this->assertEquals(['userId', 'role'], $data['claimKeys']);
    }

    public function testExtractClaimsRequestWithoutClaimKeys(): void
    {
        $request = new ExtractClaimsRequest(
            token: 'jwt-token-123',
            projectId: 'project-456',
        );

        $data = $request->toArray();

        $this->assertEquals('jwt-token-123', $data['token']);
        $this->assertEquals('project-456', $data['projectId']);
        $this->assertArrayNotHasKey('claimKeys', $data);
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
        $this->assertEquals('project-456', $data['projectId']);
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
        $this->assertEquals('project-456', $data['projectId']);
        $this->assertArrayNotHasKey('ttl', $data);
    }

    public function testValidateBatchRequestWithProjectId(): void
    {
        $request = new ValidateBatchRequest(
            tokens: ['token-1', 'token-2'],
            projectId: 'project-456',
            checkBlacklist: true,
        );

        $data = $request->toArray();

        $this->assertEquals(['token-1', 'token-2'], $data['tokens']);
        $this->assertEquals('project-456', $data['projectId']);
        $this->assertTrue($data['checkBlacklist']);
    }

    public function testValidateBatchRequestRejectsEmptyTokens(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one token is required');

        new ValidateBatchRequest(
            tokens: [],
            projectId: 'project-456',
        );
    }

    public function testValidateBatchRequestRejectsTooManyTokens(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Maximum 50 tokens allowed per batch');

        new ValidateBatchRequest(
            tokens: array_fill(0, 51, 'token'),
            projectId: 'project-456',
        );
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
            new ValidateBatchRequest(['token-1'], 'project-456'),
        ];

        foreach ($requests as $request) {
            $data = $request->toArray();
            $this->assertArrayHasKey('projectId', $data, get_class($request) . ' must include projectId');
            $this->assertEquals('project-456', $data['projectId'], get_class($request) . ' must have correct projectId');
        }
    }
}
