<?php

namespace App\Tests\Integration\Security;

use App\DTO\FacebookUserData;
use App\Entity\User;
use App\Enum\AuthProvider;
use App\Service\FacebookOAuthService;
use App\Tests\Integration\DatabaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class FacebookOAuthFlowTest extends DatabaseWebTestCase
{
    /**
     * Success path: /auth/facebook/callback?code=... creates user+identity and redirects to dashboard.
     * @return void
     */
    public function testFacebookCallbackSuccessPersistsUserAndRedirects(): void
    {
        $mock = $this->createMock(FacebookOAuthService::class);
        $mock->method('getUserFromCode')->willReturn(
            new FacebookUserData(
                id: 'fb-007',
                email: 'luigi@example.com',
                name: 'Luigi',
                picture: 'https://pic',
                accessToken: 'AT'
            )
        );
        static::getContainer()->set(FacebookOAuthService::class, $mock);

        $this->client->followRedirects(false);
        $this->client->request('GET', '/auth/facebook/callback?code=OK');

        $this->assertResponseRedirects('/');

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine')->getManager();
        $repo = $em->getRepository(User::class);
        /** @var User|null $user */
        $user = $repo->findOneBy(['email' => 'luigi@example.com']);

        $this->assertNotNull($user);
        $this->assertSame(AuthProvider::FACEBOOK, $user->getPrimaryProvider());
        $this->assertNotNull($user->getIdentity());
        $this->assertSame('fb-007', $user->getIdentity()->getProviderUserId());
    }

    /**
     * Failure path: /auth/facebook/callback?error=... flashes error and redirects to login.
     * @return void
     */
    public function testFacebookCallbackErrorRedirectsToLoginWithFlash(): void
    {
        $this->client->followRedirects(false);
        $this->client->request('GET', '/auth/facebook/callback?error=access_denied');

        $this->assertResponseRedirects('/login');
        $this->client->followRedirect();
        $this->assertSelectorExists('.alert');
    }
}
