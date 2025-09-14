<?php

namespace App\Security;

use App\Service\GoogleOAuthService;
use App\Service\SocialUserService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class GoogleOAuthAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly GoogleOAuthService $googleOAuthService,
        private readonly SocialUserService $socialUserService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    public function supports(Request $request): ?bool
    {
        $isCallbackRoute = $request->attributes->get('_route') === 'google_auth_callback';
        $hasCode = $request->query->has('code');
        
        return $isCallbackRoute && $hasCode;
    }

    public function authenticate(Request $request): Passport
    {
        $code = $request->query->get('code');
        $error = $request->query->get('error');

        if ($error) {
            throw new AuthenticationException('Google OAuth error: ' . $error);
        }

        if (!$code) {
            throw new AuthenticationException('Missing authorization code from Google');
        }

        try {
            $googleUser = $this->googleOAuthService->getUserFromCode($code);
            $user = $this->socialUserService->findOrCreateGoogleUser($googleUser);

            return new SelfValidatingPassport(
                new UserBadge($user->getUserIdentifier(), fn() => $user)
            );
        } catch (\Exception $e) {
            throw new AuthenticationException('Google authentication failed: ' . $e->getMessage(), previous: $e);
        }
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return new RedirectResponse($this->urlGenerator->generate('dashboard'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $request->getSession()->getFlashBag()->add('error', $exception->getMessage());
        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }
}