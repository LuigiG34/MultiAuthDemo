<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/')]
final class DefaultController extends AbstractController
{
    #[Route('', name: 'dashboard', methods: ['GET'])]
    public function index(): Response 
    {
        $user = $this->getUser();
        
        return $this->render('dashboard/index.html.twig', [
            'user' => $user,
        ]);
    }
}
