<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Enum\AuthProvider;
use App\Repository\UserRepository;
use App\Security\LoginFormAuthenticator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

final class LoginFormAuthenticatorTest extends TestCase
{
    /**
     * Builds a JSON POST /login Request with an in-memory session.
     * @param array $payload
     * @return Request
     */
    private function mkJsonRequest(array $payload): Request
    {
        $r = Request::create(
            '/login',
            'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, JSON_THROW_ON_ERROR)
        );
        $r->setSession(new Session(new MockArraySessionStorage()));
        return $r;
    }

    /**
     * Executes the UserBadge loader to trigger user lookup/validation exceptions.
     * @param \Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge $badge
     * @return mixed
     */
    private function runUserLoader(UserBadge $badge): mixed
    {
        $loader = method_exists($badge, 'getUserLoader')
            ? $badge->getUserLoader()
            : (static function ($b) {
                $m = new \ReflectionMethod($b, 'getUserLoader');
                $m->setAccessible(true);
                return $m->invoke($b);
            })($badge);

        return $loader($badge->getUserIdentifier());
    }

    /**
     * Asserts unknown email triggers UserNotFoundException via loader.
     * @return void
     */
    public function testAuthenticateRejectsUnknownUser(): void
    {
        $users = $this->createMock(UserRepository::class);
        $users->method('findOneBy')->willReturn(null);

        $urls = $this->createMock(UrlGeneratorInterface::class);
        $auth = new LoginFormAuthenticator($urls, $users);

        $passport = $auth->authenticate($this->mkJsonRequest([
            'email' => 'no@x.com',
            'password' => 'x',
            '_csrf_token' => 't',
        ]));

        $badge = $passport->getBadge(UserBadge::class);

        $this->expectException(UserNotFoundException::class);
        $this->runUserLoader($badge);
    }

    /**
     * Asserts social-only account triggers a CustomUserMessageAuthenticationException.
     * @return void
     */
    public function testAuthenticateRejectsSocialUser(): void
    {
        $u = new User();
        $u->setEmail('x@x.com');
        $u->setPrimaryProvider(AuthProvider::GOOGLE);

        $users = $this->createMock(UserRepository::class);
        $users->method('findOneBy')->willReturn($u);

        $urls = $this->createMock(UrlGeneratorInterface::class);
        $auth = new LoginFormAuthenticator($urls, $users);

        $passport = $auth->authenticate($this->mkJsonRequest([
            'email' => 'x@x.com',
            'password' => 'x',
            '_csrf_token' => 't',
        ]));

        $badge = $passport->getBadge(UserBadge::class);

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('This account uses social login');
        $this->runUserLoader($badge);
    }

    /**
     * Verifies local user produces a valid Passport (no exception).
     * @return void
     */
    public function testAuthenticateAcceptsLocalUser(): void
    {
        $u = new User();
        $u->setEmail('x@x.com');
        $u->setPrimaryProvider(AuthProvider::LOCAL);

        $users = $this->createMock(UserRepository::class);
        $users->method('findOneBy')->willReturn($u);

        $urls = $this->createMock(UrlGeneratorInterface::class);
        $auth = new LoginFormAuthenticator($urls, $users);

        $passport = $auth->authenticate($this->mkJsonRequest([
            'email' => 'x@x.com',
            'password' => 'secret',
            '_csrf_token' => 'token',
        ]));

        $this->assertNotNull($passport);
    }
}
