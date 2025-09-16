<?php

namespace App\Tests\Functional\Registration;

use App\Entity\User;
use App\Enum\AuthProvider;
use App\Tests\Integration\DatabaseWebTestCase;

final class RegistrationFlowTest extends DatabaseWebTestCase
{
    /**
     * GET /register should render the form and include a CSRF token input.
     * @return void
     */
    public function testRegisterPageRenders(): void
    {
        $crawler = $this->client->request('GET', '/register');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
        $this->assertSelectorExists('input[name="register[_token]"]');
    }

    /**
     * Submitting invalid data should show validation errors.
     * @return void
     */
    public function testRegisterInvalidDataShowsErrors(): void
    {
        $crawler = $this->client->request('GET', '/register');

        $form = $crawler->selectButton('Register')->form([
            'register[email]'         => 'not-an-email',
            'register[plainPassword]' => 'short',
        ]);

        $crawler = $this->client->submit($form);

        // form shows errors near fields
        $this->assertSelectorExists('form ul li');
    }

    /**
     * Valid submit creates LOCAL user, flashes success, redirects to /login.
     * @return void
     */
    public function testRegisterSuccessCreatesUserAndRedirects(): void
    {
        $crawler = $this->client->request('GET', '/register');

        $form = $crawler->selectButton('Register')->form([
            'register[email]'         => 'local@example.com',
            'register[plainPassword]' => 'Secret123!',
        ]);

        $this->client->submit($form);
        $this->assertResponseRedirects('/login');

        $this->client->followRedirect();
        $this->assertSelectorTextContains('.alert, .alert-success', 'Account created');

        // DB assertions
        $repo = $this->em->getRepository(User::class);
        /** @var User $user */
        $user = $repo->findOneBy(['email' => 'local@example.com']);
        $this->assertNotNull($user);
        $this->assertSame(AuthProvider::LOCAL, $user->getPrimaryProvider());
        $this->assertNotNull($user->getPassword());
        $this->assertTrue(in_array('ROLE_USER', $user->getRoles(), true));
    }
}
