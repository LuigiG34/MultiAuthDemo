<?php

namespace App\Controller;

use App\Service\GoogleOAuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/auth/google')]
final class GoogleAuthController extends AbstractController
{
    public function __construct(
        private readonly GoogleOAuthService $googleOAuthService,
    ) {}

    #[Route('/redirect', name: 'google_auth_redirect', methods: ['GET'])]
    public function redirectToGoogle(): RedirectResponse
    {
        $authUrl = $this->googleOAuthService->getAuthorizationUrl();
        return $this->redirect($authUrl);
    }

    #[Route('/callback', name: 'google_auth_callback', methods: ['GET'])]
    public function handleCallback(Request $request): Response
    {        
        $this->addFlash('error', 'Google authentication failed. Please try again.');
        return $this->redirectToRoute('app_login');
    }
}