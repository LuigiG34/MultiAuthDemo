<?php

namespace App\Tests\Security;

use App\DTO\FacebookUserData;
use App\Entity\User;
use App\Enum\AuthProvider;
use App\Security\FacebookOAuthAuthenticator;
use App\Service\FacebookOAuthService;
use App\Service\SocialUserService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

final class FacebookOAuthAuthenticatorTest extends TestCase
{
    /**
     * Build a Request with given route/query params and attach an in-memory session.
     * @param array $q
     * @param string $route
     * @return Request
     */
    private function mkReq(array $q, string $route='facebook_auth_callback'): Request
    {
        $r = new Request($q, [], ['_route' => $route]);
        $r->setSession(new Session(new MockArraySessionStorage()));
        return $r;
    }

    /**
     * Verifies the authenticator declares support only on the callback route with code or error.
     * @return void
     */
    public function testSupports(): void
    {
        $auth = new FacebookOAuthAuthenticator(
            $this->createMock(FacebookOAuthService::class),
            $this->createMock(SocialUserService::class),
            $this->createMock(UrlGeneratorInterface::class)
        );

        $this->assertTrue($auth->supports($this->mkReq(['code'=>'OK'])));
        $this->assertTrue($auth->supports($this->mkReq(['error'=>'denied'])));
        $this->assertFalse($auth->supports($this->mkReq([], 'other')));
    }

    /**
     * Mocks FB + Social services, simulates a valid code, asserts a Passport is created.
     * @return void
     */
    public function testAuthenticateSuccess(): void
    {
        $fb = $this->createMock(FacebookOAuthService::class);
        $social = $this->createMock(SocialUserService::class);
        $urls = $this->createMock(UrlGeneratorInterface::class);

        $fb->method('getUserFromCode')->with('CODE')->willReturn(
            new FacebookUserData('fid', 'luigi@example.com', 'Luigi', null, 'AT')
        );

        $u = new User();
        $u->setPrimaryProvider(AuthProvider::FACEBOOK);
        $u->setEmail('luigi@example.com');
        $social->method('findOrCreateFacebookUser')->willReturn($u);

        $auth = new FacebookOAuthAuthenticator($fb, $social, $urls);
        $passport = $auth->authenticate($this->mkReq(['code'=>'CODE']));

        $this->assertNotNull($passport);
    }

    /**
     * Ensures authenticate() throws when the callback has an error param.
     * @return void
     */
    public function testAuthenticateErrorParamThrows(): void
    {
        $auth = new FacebookOAuthAuthenticator(
            $this->createMock(FacebookOAuthService::class),
            $this->createMock(SocialUserService::class),
            $this->createMock(UrlGeneratorInterface::class)
        );

        $this->expectException(AuthenticationException::class);
        $auth->authenticate($this->mkReq(['error'=>'denied']));
    }

    /**
     * Checks failure adds a flash error and redirects to the login page.
     * @return void
     */
    public function testOnFailureRedirectsLoginAndFlashes(): void
    {
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->method('generate')->with('app_login')->willReturn('/login');

        $auth = new FacebookOAuthAuthenticator(
            $this->createMock(FacebookOAuthService::class),
            $this->createMock(SocialUserService::class),
            $urls
        );

        $req = $this->mkReq(['code'=>'x']);
        $resp = $auth->onAuthenticationFailure($req, new AuthenticationException('Nope'));
        $this->assertInstanceOf(RedirectResponse::class, $resp);
        $this->assertSame('/login', $resp->getTargetUrl());
        $this->assertSame(['Nope'], $req->getSession()->getFlashBag()->get('error'));
    }
}
