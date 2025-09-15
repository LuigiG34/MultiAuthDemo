<?php

namespace App\Tests\Security;

use App\DTO\GoogleUserData;
use App\Entity\User;
use App\Enum\AuthProvider;
use App\Security\GoogleOAuthAuthenticator;
use App\Service\GoogleOAuthService;
use App\Service\SocialUserService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

final class GoogleOAuthAuthenticatorTest extends TestCase
{
    private function mkReq(array $q, string $route='google_auth_callback'): Request
    {
        $r = new Request($q, [], ['_route' => $route]);
        $r->setSession(new Session(new MockArraySessionStorage()));
        return $r;
    }

    public function testSupports(): void
    {
        $auth = new GoogleOAuthAuthenticator(
            $this->createMock(GoogleOAuthService::class),
            $this->createMock(SocialUserService::class),
            $this->createMock(UrlGeneratorInterface::class),
        );

        $this->assertTrue($auth->supports($this->mkReq(['code' => 'ok'])));
        $this->assertFalse($auth->supports($this->mkReq([], 'other')));
        $this->assertFalse($auth->supports($this->mkReq([], 'google_auth_callback')));
    }

    public function testAuthenticateFailsWhenErrorParam(): void
    {
        $auth = new GoogleOAuthAuthenticator(
            $this->createMock(GoogleOAuthService::class),
            $this->createMock(SocialUserService::class),
            $this->createMock(UrlGeneratorInterface::class),
        );

        $this->expectException(AuthenticationException::class);
        $auth->authenticate($this->mkReq(['error' => 'access_denied']));
    }

    public function testAuthenticateSuccess(): void
    {
        $google = $this->createMock(GoogleOAuthService::class);
        $social = $this->createMock(SocialUserService::class);
        $urls   = $this->createMock(UrlGeneratorInterface::class);

        $google->method('getUserFromCode')->with('CODE')->willReturn(
            new GoogleUserData('gid', 'luigi@example.com', 'Luigi', null, true, 'AT', null, null)
        );

        $u = new User();
        $u->setEmail('luigi@example.com');
        $u->setPrimaryProvider(AuthProvider::GOOGLE);
        $social->method('findOrCreateGoogleUser')->willReturn($u);

        $auth = new GoogleOAuthAuthenticator($google, $social, $urls);
        $passport = $auth->authenticate($this->mkReq(['code' => 'CODE']));

        $this->assertNotNull($passport);
    }

    public function testOnSuccessRedirectsToDashboard(): void
    {
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->method('generate')->with('dashboard')->willReturn('/dashboard');

        $auth = new GoogleOAuthAuthenticator(
            $this->createMock(GoogleOAuthService::class),
            $this->createMock(SocialUserService::class),
            $urls
        );

        $resp = $auth->onAuthenticationSuccess($this->mkReq(['code'=>'c']), $this->createMock(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class), 'main');
        $this->assertInstanceOf(RedirectResponse::class, $resp);
        $this->assertSame('/dashboard', $resp->getTargetUrl());
    }

    public function testOnFailureRedirectsToLoginAndAddsFlash(): void
    {
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->method('generate')->with('app_login')->willReturn('/login');

        $auth = new GoogleOAuthAuthenticator(
            $this->createMock(GoogleOAuthService::class),
            $this->createMock(SocialUserService::class),
            $urls
        );

        $req = $this->mkReq(['code' => 'x']);
        $resp = $auth->onAuthenticationFailure($req, new AuthenticationException('Oops'));

        $this->assertInstanceOf(RedirectResponse::class, $resp);
        $this->assertSame('/login', $resp->getTargetUrl());
        $this->assertSame(['Oops'], $req->getSession()->getFlashBag()->get('error'));
    }
}
