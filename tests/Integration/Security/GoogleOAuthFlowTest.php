<?php

namespace App\Tests\Integration\Security;

use App\DTO\GoogleUserData;
use App\Entity\User;
use App\Enum\AuthProvider;
use App\Service\GoogleOAuthService;
use App\Tests\Integration\DatabaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class GoogleOAuthFlowTest extends DatabaseWebTestCase
{
    /**
     * Success path: /auth/google/callback?code=... creates user+identity and redirects to dashboard.
     * @return void
     */
    public function testGoogleCallbackSuccessPersistsUserAndRedirects(): void
    {
        $mock = $this->createMock(GoogleOAuthService::class);
        $mock->method('getUserFromCode')->willReturn(
            new GoogleUserData(
                id: 'gid-42',
                email: 'luigi@example.com',
                name: 'Luigi',
                picture: 'https://pic',
                verifiedEmail: true,
                accessToken: 'AT',
                refreshToken: 'RT',
                expiresAt: null
            )
        );
        static::getContainer()->set(GoogleOAuthService::class, $mock);

        $this->client->followRedirects(false);
        $this->client->request('GET', '/auth/google/callback?code=OK');

        // Authenticator should redirect to dashboard
        $this->assertResponseRedirects('/');

        // Check DB persisted user + identity
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine')->getManager();
        $repo = $em->getRepository(User::class);
        /** @var User|null $user */
        $user = $repo->findOneBy(['email' => 'luigi@example.com']);

        $this->assertNotNull($user);
        $this->assertSame(AuthProvider::GOOGLE, $user->getPrimaryProvider());
        $this->assertNotNull($user->getIdentity());
        $this->assertSame('gid-42', $user->getIdentity()->getProviderUserId());
    }

    /** 
     * Failure path: /auth/google/callback?error=... flashes error and redirects to login.
     * @return void
     */
    public function testGoogleCallbackErrorRedirectsToLoginWithFlash(): void
    {
        $this->client->followRedirects(false);
        $this->client->request('GET', '/auth/google/callback?error=access_denied');

        $this->assertResponseRedirects('/login');

        $this->client->followRedirect();
        $this->assertSelectorExists('.alert');
    }
}
