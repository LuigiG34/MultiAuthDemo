<?php

namespace App\Tests\Functional\Security;

use App\Entity\User;
use App\Enum\AuthProvider;
use App\Tests\Integration\DatabaseWebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AuthenticationFlowTest extends DatabaseWebTestCase
{
    /**
     * The /login page renders and has Google/Facebook buttons.
     * @return void
     */
    public function testLoginPageRendersWithSocialButtons(): void
    {
        $this->client->request('GET', '/login');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('a[href="/auth/google/redirect"]');
        $this->assertSelectorExists('a[href="/auth/facebook/redirect"]');
    }

    /**
     * Wrong password shows an error on the login page.
     * @return void
     */
    public function testLoginWrongPasswordShowsError(): void
    {
        // seed a local user
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = new User();
        $user->setEmail('local@example.com');
        $user->setPrimaryProvider(AuthProvider::LOCAL);
        $user->setPassword($hasher->hashPassword($user, 'Secret123!'));
        $this->em->persist($user);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/login');

        $form = $crawler->selectButton('Sign in')->form([
            'email'    => 'local@example.com',
            'password' => 'WrongPass!',
        ]);

        $this->client->submit($form);
        // to /login and shows error block
        if ($this->client->getResponse()->isRedirection()) {
            $this->client->followRedirect();
        }
        $this->assertSelectorExists('.alert, .alert-danger');
    }

    /**
     * Social user attempting password login should get the custom message.
     * @return void
     */
    public function testLoginSocialUserShowsCustomMessage(): void
    {
        $user = new User();
        $user->setEmail('social@example.com');
        $user->setPrimaryProvider(AuthProvider::GOOGLE);
        $this->em->persist($user);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/login');

        $form = $crawler->selectButton('Sign in')->form([
            'email'    => 'social@example.com',
            'password' => 'irrelevant',
        ]);

        $this->client->submit($form);
        if ($this->client->getResponse()->isRedirection()) {
            $this->client->followRedirect();
        }
        $this->assertSelectorTextContains('.alert, .alert-danger', 'social login');
    }

    /**
     * form login succeeds and redirects to dashboard which shows the user email.
     * @return void
     */
    public function testLoginSuccessShowsDashboard(): void
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = new User();
        $user->setEmail('local2@example.com');
        $user->setPrimaryProvider(AuthProvider::LOCAL);
        $user->setPassword($hasher->hashPassword($user, 'Secret123!'));
        $this->em->persist($user);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/login');

        $form = $crawler->selectButton('Sign in')->form([
            'email'    => 'local2@example.com',
            'password' => 'Secret123!',
        ]);

        $this->client->submit($form);
        $this->assertResponseRedirects('/');

        $crawler = $this->client->followRedirect();
        $this->assertSelectorTextContains('body', 'Welcome, local2@example.com');
    }
}
