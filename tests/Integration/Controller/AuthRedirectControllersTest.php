<?php

namespace App\Tests\Integration\Controller;

use App\Service\FacebookOAuthService;
use App\Service\GoogleOAuthService;
use App\Tests\Integration\DatabaseWebTestCase;

final class AuthRedirectControllersTest extends DatabaseWebTestCase
{
    /**
     * Google redirect should 302 to Google authorization URL (mocked).
     * @return void
     */
    public function testGoogleRedirect(): void
    {
        $mock = $this->createMock(GoogleOAuthService::class);
        $mock->method('getAuthorizationUrl')->willReturn('https://google.example/auth');

        static::getContainer()->set(GoogleOAuthService::class, $mock);

        $this->client->request('GET', '/auth/google/redirect');
        $this->assertResponseRedirects('https://google.example/auth');
    }

    /**
     * Facebook redirect should 302 to Facebook authorization URL (mocked).
     * @return void
     */
    public function testFacebookRedirect(): void
    {
        $mock = $this->createMock(FacebookOAuthService::class);
        $mock->method('getAuthorizationUrl')->willReturn('https://facebook.example/auth');

        static::getContainer()->set(FacebookOAuthService::class, $mock);

        $this->client->request('GET', '/auth/facebook/redirect');
        $this->assertResponseRedirects('https://facebook.example/auth');
    }
}
