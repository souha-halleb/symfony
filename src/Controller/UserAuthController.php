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
 * Contrôleur des pages HTML d'auth utilisateur.
 * L'authentification réelle se fait via /api/auth/login (JWT + JS fetch).
 * Ces routes servent uniquement à afficher les templates Twig.
 */
class UserAuthController extends AbstractController
{
    /**
     * Page de connexion utilisateur (affichage Twig uniquement).
     * L'auth se fait en JS via fetch('/api/auth/login').
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
     * FIX #20 : logout() était une méthode vide (retournait void sans rien faire).
     * Symfony intercepte normalement /logout via le firewall, mais puisqu'on
     * utilise du JWT stocké en localStorage (pas de session Symfony), la déconnexion
     * se fait côté JS (suppression des tokens). On redirige simplement vers home.
     *
     * Note : si le firewall "main" a un logout configuré, Symfony intercept cette
     * route avant qu'elle n'arrive ici → cette méthode ne sera jamais exécutée.
     * Elle est gardée pour la déclaration de route.
     */
    #[Route('/logout', name: 'user_logout')]
    public function logout(): Response
    {
        // Symfony intercepte cette route via le firewall logout.
        // Si on arrive ici (ex: pas de session), on redirige vers home.
        return $this->redirectToRoute('home');
    }

    /**
     * Page d'inscription utilisateur.
     * Méthode POST : inscription classique email+password (pas JWT, juste redirection).
     * L'inscription Passkey se fait via /api/auth/register/options (JS).
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
            $email    = trim($request->request->get('email', ''));
            $password = $request->request->get('password', '');
            $confirm  = $request->request->get('confirm', '');

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Adresse email invalide.';
            } elseif ($password !== $confirm) {
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