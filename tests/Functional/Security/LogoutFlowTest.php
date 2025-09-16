<?php

namespace App\Tests\Functional\Security;

use App\Entity\User;
use App\Enum\AuthProvider;
use App\Tests\Integration\DatabaseWebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class LogoutFlowTest extends DatabaseWebTestCase
{
    /**
     * After login, /logout should invalidate the session and (usually) redirect to /login.
     * @return void
     */
    public function testLogoutInvalidatesSession(): void
    {
        // login first
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $u = new User();
        $u->setEmail('logout@example.com');
        $u->setPrimaryProvider(AuthProvider::LOCAL);
        $u->setPassword($hasher->hashPassword($u, 'Secret123!'));
        $this->em->persist($u);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->selectButton('Sign in')->form([
            'email'    => 'logout@example.com',
            'password' => 'Secret123!',
        ]);
        $this->client->submit($form);
        $this->assertResponseRedirects('/');
        $this->client->followRedirect();

        // hit logout
        $this->client->request('GET', '/logout');
        // firewall usually redirects to /login after logout 
        $this->assertTrue($this->client->getResponse()->isRedirection());
        $this->client->followRedirect();

        // we should be on login
        $this->assertSelectorExists('form input[name="email"]');
    }
}
