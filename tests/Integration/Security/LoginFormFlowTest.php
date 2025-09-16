<?php

namespace App\Tests\Integration\Security;

use App\Tests\Integration\DatabaseWebTestCase;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class LoginFormFlowTest extends DatabaseWebTestCase
{
    /**
     * Happy-path local login: create a user in DB, then POST JSON /login with CSRF and expect redirect.
     * @return void
     */
    public function testLocalLoginSuccess(): void
    {
        // Seed a local user
        $em = static::getContainer()->get('doctrine')->getManager();
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new \App\Entity\User();
        $user->setEmail('local@example.com');
        $user->setPrimaryProvider(\App\Enum\AuthProvider::LOCAL);
        $user->setPassword($hasher->hashPassword($user, 'Secret123!'));
        $em->persist($user);
        $em->flush();

        // Load the login page
        $this->client->followRedirects(false);
        $crawler = $this->client->request('GET', '/login');

        // Submit the form
        $form = $crawler->selectButton('Sign in')->form([
            'email'    => 'local@example.com',
            'password' => 'Secret123!',
        ]);

        $this->client->submit($form);

        // Should redirect to dashboard
        $this->assertResponseRedirects('/');
    }

    /**
     * Unknown email -> authentication failure => redirect back to /login with flash.
     * @return void
     */
    public function testLocalLoginUnknownUser(): void
    {
        /** @var CsrfTokenManagerInterface $csrf */
        $csrf = static::getContainer()->get(CsrfTokenManagerInterface::class);
        $token = $csrf->getToken('authenticate')->getValue();

        $this->client->followRedirects(false);
        $this->client->request(
            'POST',
            '/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'email' => 'nope@example.com',
                'password' => 'x',
                '_csrf_token' => $token,
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertResponseRedirects('/login');
        $this->client->followRedirect();
        $this->assertSelectorExists('.alert');
    }
}
