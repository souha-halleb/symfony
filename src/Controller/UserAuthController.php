<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
/**
 * controleur gere l’authentification et l’inscription des utilisateurs
 * Routes principales : login, logout, register
 */
class UserAuthController extends AbstractController
{
    /**
     * Page de connexion utilisateur
     * Route : GET /login
     */
    #[Route('/login', name: 'user_login')]
    public function login(AuthenticationUtils $authUtils): Response
    {
        if ($this->isGranted('ROLE_USER')) {
            return $this->redirectToRoute('home');
        }

        return $this->render('auth/user_login.html.twig', [
            'last_username' => $authUtils->getLastUsername(),
            'error'         => $authUtils->getLastAuthenticationError(),
        ]);
    }
/**
     * Déconnexion utilisateur
     * Route : GET /logout*/
    #[Route('/logout', name: 'user_logout')]
    public function logout(): void {}
    /**
     * Page d inscription utilisateur
     * Route : GET + POST /register
     */
    #[Route('/register', name: 'user_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        if ($this->isGranted('ROLE_USER')) {
            return $this->redirectToRoute('home');
        }

        $error = null;

        if ($request->isMethod('POST')) {
            $email    = $request->request->get('email', '');
            $password = $request->request->get('password', '');
            $confirm  = $request->request->get('confirm', '');

            if ($password !== $confirm) {
                $error = 'Les mots de passe ne correspondent pas.';
            } elseif (strlen($password) < 6) {
                $error = 'Le mot de passe doit contenir au moins 6 caractères.';
            } elseif ($em->getRepository(User::class)->findOneBy(['email' => $email])) {
                $error = 'Cet email est déjà utilisé.';
            } else {
                $user = new User($email);
                $user->setPassword($hasher->hashPassword($user, $password));
                $em->persist($user);
                $em->flush();

                $this->addFlash('success', 'Compte créé ! Vous pouvez vous connecter.');
                return $this->redirectToRoute('user_login');
            }
        }

        return $this->render('auth/user_register.html.twig', [
            'error' => $error,
        ]);
    }
}