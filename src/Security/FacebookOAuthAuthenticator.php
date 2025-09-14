<?php

namespace App\Security;

use App\Service\FacebookOAuthService;
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

final class FacebookOAuthAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly FacebookOAuthService $facebookOAuthService,
        private readonly SocialUserService $socialUserService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    public function supports(Request $request): ?bool
    {
        $route = $request->attributes->get('_route');
        $hasCode = $request->query->has('code');
        $hasError = $request->query->has('error');
        
        $supports = $route === 'facebook_auth_callback' && ($hasCode || $hasError);
        
        return $supports;
    }

    public function authenticate(Request $request): Passport
    {
        $error = $request->query->get('error');
        $code = $request->query->get('code');

        if ($error) {
            throw new AuthenticationException('Facebook OAuth error: ' . $error);
        }

        if (!$code) {
            throw new AuthenticationException('Missing authorization code from Facebook');
        }

        try {
            $facebookUser = $this->facebookOAuthService->getUserFromCode($code);
            $user = $this->socialUserService->findOrCreateFacebookUser($facebookUser);

            return new SelfValidatingPassport(
                new UserBadge($user->getUserIdentifier(), fn() => $user)
            );
        } catch (\Exception $e) {
            throw new AuthenticationException('Facebook authentication failed: ' . $e->getMessage(), previous: $e);
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