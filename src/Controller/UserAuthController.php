<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UserAuthController extends AbstractController
{
    #[Route('/login', name: 'user_login')]
    public function login(): Response
    {
        return $this->render('auth/user_login.html.twig', [
            'last_username' => '',
            'error'         => null,
        ]);
    }

    #[Route('/logout', name: 'user_logout')]
    public function logout(): Response
    {
        return $this->redirectToRoute('home');
    }

    #[Route('/register', name: 'user_register')]
    public function register(): Response
    {
        return $this->render('auth/user_register.html.twig', [
            'error' => null,
        ]);
    }
}