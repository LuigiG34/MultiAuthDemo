<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Enum\AuthProvider;
use App\Repository\UserRepository;
use App\Security\LoginFormAuthenticator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

final class LoginFormAuthenticatorTest extends TestCase
{
    private function mkRequest(array $payload): Request
    {
        $r = new Request([], $payload);
        $r->setSession(new Session(new MockArraySessionStorage()));
        return $r;
    }

    public function testAuthenticateRejectsUnknownUser(): void
    {
        $users = $this->createMock(UserRepository::class);
        $users->method('findOneBy')->willReturn(null);

        $urls = $this->createMock(UrlGeneratorInterface::class);
        $auth = new LoginFormAuthenticator($urls, $users);

        $this->expectException(UserNotFoundException::class);
        $auth->authenticate($this->mkRequest([
            'email' => 'no@x.com',
            'password' => 'x',
            '_csrf_token' => 't',
        ]));
    }

    public function testAuthenticateRejectsSocialUser(): void
    {
        $u = new User();
        $u->setEmail('x@x.com');
        $u->setPrimaryProvider(AuthProvider::GOOGLE);

        $users = $this->createMock(UserRepository::class);
        $users->method('findOneBy')->willReturn($u);

        $urls = $this->createMock(UrlGeneratorInterface::class);
        $auth = new LoginFormAuthenticator($urls, $users);

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('This account uses social login.');
        $auth->authenticate($this->mkRequest([
            'email' => 'x@x.com',
            'password' => 'x',
            '_csrf_token' => 't',
        ]));
    }

    public function testAuthenticateAcceptsLocalUser(): void
    {
        $u = new User();
        $u->setEmail('x@x.com');
        $u->setPrimaryProvider(AuthProvider::LOCAL);

        $users = $this->createMock(UserRepository::class);
        $users->method('findOneBy')->willReturn($u);

        $urls = $this->createMock(UrlGeneratorInterface::class);
        $auth = new LoginFormAuthenticator($urls, $users);

        $passport = $auth->authenticate($this->mkRequest([
            'email' => 'x@x.com',
            'password' => 'secret',
            '_csrf_token' => 'token',
        ]));

        $this->assertNotNull($passport);
        $this->assertSame('x@x.com', $passport->getBadge(\Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge::class)->getUserIdentifier());
    }
}
