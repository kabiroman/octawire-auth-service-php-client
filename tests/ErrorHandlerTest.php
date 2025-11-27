<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Tests;

use Kabiroman\Octawire\AuthService\Client\ErrorHandler;
use Kabiroman\Octawire\AuthService\Client\Exception\AuthException;
use Kabiroman\Octawire\AuthService\Client\Exception\ConnectionException;
use Kabiroman\Octawire\AuthService\Client\Exception\InvalidTokenException;
use Kabiroman\Octawire\AuthService\Client\Exception\RateLimitException;
use Kabiroman\Octawire\AuthService\Client\Exception\TokenExpiredException;
use Kabiroman\Octawire\AuthService\Client\Exception\TokenRevokedException;
use PHPUnit\Framework\TestCase;

class ErrorHandlerTest extends TestCase
{
    public function testWrapAUTH_FAILED(): void
    {
        $jatpError = [
            'code' => 'AUTH_FAILED',
            'message' => 'Invalid service credentials',
            'details' => [],
        ];

        $exception = new \Exception('Original error');
        $result = ErrorHandler::wrapError($exception, $jatpError);

        $this->assertInstanceOf(AuthException::class, $result);
        $this->assertEquals(403, $result->getCode());
        $this->assertEquals('AUTH_FAILED', $result->getErrorCode());
        $this->assertEquals('Invalid service credentials', $result->getMessage());
    }

    public function testWrapERROR_EXPIRED_TOKEN(): void
    {
        $jatpError = [
            'code' => 'ERROR_EXPIRED_TOKEN',
            'message' => 'Token has expired',
            'details' => [],
        ];

        $exception = new \Exception('Original error');
        $result = ErrorHandler::wrapError($exception, $jatpError);

        $this->assertInstanceOf(TokenExpiredException::class, $result);
        $this->assertEquals(401, $result->getCode());
    }

    public function testWrapERROR_INVALID_TOKEN(): void
    {
        $jatpError = [
            'code' => 'ERROR_INVALID_TOKEN',
            'message' => 'Invalid token format',
            'details' => [],
        ];

        $exception = new \Exception('Original error');
        $result = ErrorHandler::wrapError($exception, $jatpError);

        $this->assertInstanceOf(InvalidTokenException::class, $result);
        $this->assertEquals(400, $result->getCode());
    }

    public function testWrapERROR_INVALID_SIGNATURE(): void
    {
        $jatpError = [
            'code' => 'ERROR_INVALID_SIGNATURE',
            'message' => 'Invalid signature',
            'details' => [],
        ];

        $exception = new \Exception('Original error');
        $result = ErrorHandler::wrapError($exception, $jatpError);

        $this->assertInstanceOf(InvalidTokenException::class, $result);
        $this->assertEquals(400, $result->getCode());
    }

    public function testWrapERROR_INVALID_ISSUER(): void
    {
        $jatpError = [
            'code' => 'ERROR_INVALID_ISSUER',
            'message' => 'Invalid issuer',
            'details' => [],
        ];

        $exception = new \Exception('Original error');
        $result = ErrorHandler::wrapError($exception, $jatpError);

        $this->assertInstanceOf(InvalidTokenException::class, $result);
        $this->assertEquals(400, $result->getCode());
    }

    public function testWrapERROR_INVALID_AUDIENCE(): void
    {
        $jatpError = [
            'code' => 'ERROR_INVALID_AUDIENCE',
            'message' => 'Invalid audience',
            'details' => [],
        ];

        $exception = new \Exception('Original error');
        $result = ErrorHandler::wrapError($exception, $jatpError);

        $this->assertInstanceOf(InvalidTokenException::class, $result);
        $this->assertEquals(400, $result->getCode());
    }

    public function testWrapERROR_TOKEN_REVOKED(): void
    {
        $jatpError = [
            'code' => 'ERROR_TOKEN_REVOKED',
            'message' => 'Token has been revoked',
            'details' => [],
        ];

        $exception = new \Exception('Original error');
        $result = ErrorHandler::wrapError($exception, $jatpError);

        $this->assertInstanceOf(TokenRevokedException::class, $result);
        $this->assertEquals(401, $result->getCode());
    }

    public function testWrapERROR_REFRESH_TOKEN_INVALID(): void
    {
        $jatpError = [
            'code' => 'ERROR_REFRESH_TOKEN_INVALID',
            'message' => 'Refresh token is invalid',
            'details' => [],
        ];

        $exception = new \Exception('Original error');
        $result = ErrorHandler::wrapError($exception, $jatpError);

        $this->assertInstanceOf(InvalidTokenException::class, $result);
        $this->assertEquals(400, $result->getCode());
    }

    public function testWrapERROR_REFRESH_TOKEN_EXPIRED(): void
    {
        $jatpError = [
            'code' => 'ERROR_REFRESH_TOKEN_EXPIRED',
            'message' => 'Refresh token has expired',
            'details' => [],
        ];

        $exception = new \Exception('Original error');
        $result = ErrorHandler::wrapError($exception, $jatpError);

        $this->assertInstanceOf(TokenExpiredException::class, $result);
        $this->assertEquals(401, $result->getCode());
    }

    public function testWrapERROR_INVALID_USER_ID(): void
    {
        $jatpError = [
            'code' => 'ERROR_INVALID_USER_ID',
            'message' => 'Invalid user ID',
            'details' => [],
        ];

        $exception = new \Exception('Original error');
        $result = ErrorHandler::wrapError($exception, $jatpError);

        $this->assertInstanceOf(AuthException::class, $result);
        $this->assertEquals(400, $result->getCode());
        $this->assertEquals('ERROR_INVALID_USER_ID', $result->getErrorCode());
    }

    public function testWrapERROR_RATE_LIMIT_EXCEEDED(): void
    {
        $jatpError = [
            'code' => 'ERROR_RATE_LIMIT_EXCEEDED',
            'message' => 'Rate limit exceeded',
            'details' => [
                'limit' => 1000,
                'remaining' => 0,
                'window' => 60,
            ],
        ];

        $exception = new \Exception('Original error');
        $result = ErrorHandler::wrapError($exception, $jatpError);

        $this->assertInstanceOf(RateLimitException::class, $result);
        $this->assertEquals(429, $result->getCode());
        $this->assertEquals(1000, $result->getLimit());
        $this->assertEquals(0, $result->getRemaining());
        $this->assertEquals(60, $result->getWindow());
    }

    public function testWrapConnectionError(): void
    {
        $exception = new \Exception('Connection timeout');
        $result = ErrorHandler::wrapError($exception);

        $this->assertInstanceOf(ConnectionException::class, $result);
    }

    public function testWrapErrorWithBracketFormat(): void
    {
        $exception = new \Exception('[AUTH_FAILED] Invalid service credentials');
        $result = ErrorHandler::wrapError($exception);

        $this->assertInstanceOf(AuthException::class, $result);
        $this->assertEquals(403, $result->getCode());
        $this->assertEquals('AUTH_FAILED', $result->getErrorCode());
    }

    public function testWrapErrorInferExpiredFromMessage(): void
    {
        $exception = new \Exception('Token expired');
        $result = ErrorHandler::wrapError($exception);

        $this->assertInstanceOf(TokenExpiredException::class, $result);
        $this->assertEquals(401, $result->getCode());
    }

    public function testWrapErrorInferRevokedFromMessage(): void
    {
        $exception = new \Exception('Token revoked');
        $result = ErrorHandler::wrapError($exception);

        $this->assertInstanceOf(TokenRevokedException::class, $result);
        $this->assertEquals(401, $result->getCode());
    }

    public function testWrapErrorInferInvalidTokenFromMessage(): void
    {
        $exception = new \Exception('Invalid token');
        $result = ErrorHandler::wrapError($exception);

        $this->assertInstanceOf(InvalidTokenException::class, $result);
        $this->assertEquals(400, $result->getCode());
    }

    public function testWrapAuthExceptionReturnsAsIs(): void
    {
        $originalException = new AuthException('Already wrapped', 400);
        $result = ErrorHandler::wrapError($originalException);

        $this->assertSame($originalException, $result);
    }
}

