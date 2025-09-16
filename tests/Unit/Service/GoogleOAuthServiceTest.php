<?php

namespace App\Tests\Service;

use App\Service\GoogleOAuthService;
use Google\Client as GoogleClient;
use PHPUnit\Framework\TestCase;

final class GoogleOAuthServiceTest extends TestCase
{
    /**
     * Subclasses Google\Client to stub createAuthUrl() and fetchAccessTokenWithAuthCode().
     * @param mixed $authUrl
     * @param mixed $token
     * @return object
     */
    private static function fakeClient(?string $authUrl = null, ?array $token = null): GoogleClient
    {
        return new class($authUrl, $token) extends GoogleClient {
            public function __construct(private ?string $authUrl, private ?array $token) {}

            // MUST match parent signature
            public function createAuthUrl($scope = null, array $queryParams = []): string
            {
                return $this->authUrl ?? 'about:blank';
            }

            // MUST match parent signature
            public function fetchAccessTokenWithAuthCode($code, $codeVerifier = null): array
            {
                return $this->token ?? [];
            }
        };
    }

    /**
     * Replaces the service’s private Google client via reflection.
     * @param \App\Service\GoogleOAuthService $svc
     * @param \Google\Client $client
     * @return void
     */
    private function swapClient(GoogleOAuthService $svc, GoogleClient $client): void
    {
        $ref = new \ReflectionClass($svc);
        $prop = $ref->getProperty('client');
        $prop->setAccessible(true);
        $prop->setValue($svc, $client);
    }

    /**
     * Asserts service returns the URL produced by the client stub.
     * @return void
     */
    public function testGetAuthorizationUrlDelegatesToClient(): void
    {
        $svc = new GoogleOAuthService('CID', 'CS', 'https://app/cb');
        $this->swapClient($svc, self::fakeClient('https://accounts.google.com/o/oauth2/auth?dummy=1', []));

        $this->assertSame('https://accounts.google.com/o/oauth2/auth?dummy=1', $svc->getAuthorizationUrl());
    }

    /**
     * Ensures token error path throws RuntimeException with message.
     * @return void
     */
    public function testGetUserFromCodeThrowsOnTokenError(): void
    {
        $svc = new GoogleOAuthService('CID', 'CS', 'https://app/cb');
        $this->swapClient($svc, self::fakeClient(null, [
            'error' => 'invalid_grant',
            'error_description' => 'Bad code',
        ]));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Google OAuth error: Bad code');
        $svc->getUserFromCode('BAD');
    }
}
