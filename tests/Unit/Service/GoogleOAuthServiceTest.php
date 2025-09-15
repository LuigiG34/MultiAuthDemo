<?php

namespace App\Tests\Service;

use App\Service\GoogleOAuthService;
use PHPUnit\Framework\TestCase;

final class GoogleOAuthServiceTest extends TestCase
{
    public function testGetAuthorizationUrlDelegatesToClientViaReflection(): void
    {
        // Arrange: make the service, then swap its private $client with a double
        $svc = new GoogleOAuthService('CID', 'CS', 'https://app/cb');

        // Build a stub of Google\Client if lib is installed; otherwise, create a stdclass matching methods
        $double = new class {
            public function createAuthUrl(): string { return 'https://accounts.google.com/o/oauth2/auth?dummy=1'; }
            public function setClientId($v) {}
            public function setClientSecret($v) {}
            public function setRedirectUri($v) {}
            public function addScope($v) {}
            public function setAccessType($v) {}
            public function setPrompt($v) {}
        };

        $ref = new \ReflectionClass($svc);
        $prop = $ref->getProperty('client');
        $prop->setAccessible(true);
        $prop->setValue($svc, $double);

        $this->assertSame('https://accounts.google.com/o/oauth2/auth?dummy=1', $svc->getAuthorizationUrl());
    }

    public function testGetUserFromCodeThrowsOnTokenError(): void
    {
        $svc = new GoogleOAuthService('CID', 'CS', 'https://app/cb');

        $double = new class {
            public function setClientId($v) {}
            public function setClientSecret($v) {}
            public function setRedirectUri($v) {}
            public function addScope($v) {}
            public function setAccessType($v) {}
            public function setPrompt($v) {}
            public function fetchAccessTokenWithAuthCode($code): array
            {
                return ['error' => 'invalid_grant', 'error_description' => 'Bad code'];
            }
        };

        $ref = new \ReflectionClass($svc);
        $prop = $ref->getProperty('client');
        $prop->setAccessible(true);
        $prop->setValue($svc, $double);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Google OAuth error: Bad code');
        $svc->getUserFromCode('BAD');
    }
}
