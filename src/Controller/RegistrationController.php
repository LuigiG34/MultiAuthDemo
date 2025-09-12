<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\AuthProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use App\Form\RegisterType;

#[Route('/register')]
final class RegistrationController extends AbstractController
{
    #[Route('', name: 'auth_register', methods: ['GET','POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        $user = new User();
        $form = $this->createForm(RegisterType::class, $user, []);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPrimaryProvider(AuthProvider::LOCAL);

            $plain = $form->get('plainPassword')->getData();
            $hash  = $hasher->hashPassword($user, $plain);
            $user->setPassword($hash);

            $user->setRoles(['ROLE_USER']);
            $user->setIsVerified(false);

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Account created. You can log in now.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }
}
