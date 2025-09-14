<?php

namespace App\Controller;

use App\Service\FacebookOAuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/auth/facebook')]
final class FacebookAuthController extends AbstractController
{
    public function __construct(
        private readonly FacebookOAuthService $facebookOAuthService,
    ) {}

    #[Route('/redirect', name: 'facebook_auth_redirect', methods: ['GET'])]
    public function redirectToFacebook(): RedirectResponse
    {
        $authUrl = $this->facebookOAuthService->getAuthorizationUrl();
        return $this->redirect($authUrl);
    }

    #[Route('/callback', name: 'facebook_auth_callback', methods: ['GET'])]
    public function handleCallback(): Response
    {
        $this->addFlash('error', 'Facebook authentication failed. Please try again.');
        return $this->redirectToRoute('app_login');
    }
}