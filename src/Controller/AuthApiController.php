<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\PasskeyAuthService;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/auth')]
class AuthApiController extends AbstractController
{
    public function __construct(
        private JWTTokenManagerInterface $jwtManager,
        private RefreshTokenGeneratorInterface $refreshGenerator,
        private RefreshTokenManagerInterface $refreshManager,   // ✅ ajouté
        private EntityManagerInterface $em,
        private UserRepository $userRepo
    ) {}

    private function createAndSaveRefreshToken(User $user): string
    {
        try {
            $refresh = $this->refreshGenerator->createForUserWithTtl($user, 2592000);

            if (!$refresh) {
                return '';
            }

            $this->refreshManager->save($refresh);  // ✅ fonctionne maintenant

            return $refresh->getRefreshToken() ?? '';

        } catch (\Throwable $e) {
            error_log('REFRESH ERROR: ' . $e->getMessage());
            return '';
        }
    }

    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request, UserPasswordHasherInterface $hasher): JsonResponse
    {
        $data     = json_decode($request->getContent(), true);
        $email    = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (!$email || !$password) {
            return $this->json(['error' => 'Email et mot de passe requis.'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepo->findOneBy(['email' => $email]);

        if (!$user || $user->getPassword() === null || !$hasher->isPasswordValid($user, $password)) {
            return $this->json(['error' => 'Email ou mot de passe incorrect.'], Response::HTTP_UNAUTHORIZED);
        }

        $jwt          = $this->jwtManager->create($user);
        $refreshToken = $this->createAndSaveRefreshToken($user);

        return $this->json([
            'success'       => true,
            'token'         => $jwt,
            'refresh_token' => $refreshToken,
            'user'          => [
                'id'    => (string) $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ],
        ]);
    }

    #[Route('/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request, UserPasswordHasherInterface $hasher): JsonResponse
    {
        $data     = json_decode($request->getContent(), true);
        $email    = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (!$email || !$password) {
            return $this->json(['error' => 'Email et mot de passe requis.'], Response::HTTP_BAD_REQUEST);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Email invalide.'], Response::HTTP_BAD_REQUEST);
        }

        if (strlen($password) < 6) {
            return $this->json(['error' => 'Mot de passe trop court (min. 6 caractères).'], Response::HTTP_BAD_REQUEST);
        }

        if ($this->userRepo->findOneBy(['email' => $email])) {
            return $this->json(['error' => 'Email déjà utilisé.'], Response::HTTP_CONFLICT);
        }

        $user = new User($email);
        $user->setPassword($hasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();

        $jwt          = $this->jwtManager->create($user);
        $refreshToken = $this->createAndSaveRefreshToken($user);

        return $this->json([
            'success'       => true,
            'token'         => $jwt,
            'refresh_token' => $refreshToken,
            'user'          => [
                'id'    => (string) $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ],
        ], Response::HTTP_CREATED);
    }

    // ── Passkey (WebAuthn) ──

    #[Route('/register/options', name: 'api_register_options', methods: ['POST'])]
    public function registerOptions(Request $request, PasskeyAuthService $passkeyService): JsonResponse
    {
        $data  = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Email valide requis.'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepo->findOneBy(['email' => $email]);
        if (!$user) {
            $user = new User($email);
            $this->em->persist($user);
            $this->em->flush();
        }

        try {
            return $this->json($passkeyService->getRegistrationOptions($user));
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/register/verify', name: 'api_register_verify', methods: ['POST'])]
    public function registerVerify(Request $request, PasskeyAuthService $passkeyService): JsonResponse
    {
        $data       = json_decode($request->getContent(), true);
        $email      = $data['email'] ?? null;
        $credential = $data['credential'] ?? null;

        $user = $this->userRepo->findOneBy(['email' => $email]);
        if (!$user || !$credential) {
            return $this->json(['error' => 'Données invalides.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $passkeyService->verifyRegistration(json_encode($credential), $user);

            $jwt          = $this->jwtManager->create($user);
            $refreshToken = $this->createAndSaveRefreshToken($user);

            return $this->json([
                'success'       => true,
                'token'         => $jwt,
                'refresh_token' => $refreshToken,
                'user'          => [
                    'id'    => (string) $user->getId(),
                    'email' => $user->getEmail(),
                    'roles' => $user->getRoles(),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/login/options', name: 'api_login_options', methods: ['POST'])]
    public function loginOptions(PasskeyAuthService $passkeyService): JsonResponse
    {
        try {
            return $this->json($passkeyService->getLoginOptions());
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/login/verify', name: 'api_login_verify', methods: ['POST'])]
    public function loginVerify(Request $request, PasskeyAuthService $passkeyService): JsonResponse
    {
        $data       = json_decode($request->getContent(), true);
        $credential = $data['credential'] ?? null;

        if (!$credential) {
            return $this->json(['error' => 'Credential requis.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user         = $passkeyService->verifyLogin(json_encode($credential));
            $jwt          = $this->jwtManager->create($user);
            $refreshToken = $this->createAndSaveRefreshToken($user);

            return $this->json([
                'success'       => true,
                'token'         => $jwt,
                'refresh_token' => $refreshToken,
                'user'          => [
                    'id'    => (string) $user->getId(),
                    'email' => $user->getEmail(),
                    'roles' => $user->getRoles(),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNAUTHORIZED);
        }
    }

    #[Route('/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'id'    => (string) $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ]);
    }
}