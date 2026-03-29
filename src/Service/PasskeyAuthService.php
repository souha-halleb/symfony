<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\WebauthnCredentialRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorAssertionResponseValidator;

class PasskeyAuthService
{
    // ✅ Adapte ces valeurs à ton projet
    private const RP_NAME = 'EventReservation';
    private const RP_ID   = 'localhost';
    private const ORIGIN  = 'http://localhost:8080';

    public function __construct(
        private AuthenticatorAttestationResponseValidator $attestationValidator,
        private AuthenticatorAssertionResponseValidator   $assertionValidator,
        private SerializerInterface                       $serializer,
        private RequestStack                              $requestStack,
        private WebauthnCredentialRepository              $credRepo,
        private EntityManagerInterface                    $em,
    ) {}

    /* ── REGISTER OPTIONS ── */

    public function getRegistrationOptions(User $user): array
{
    $rp = PublicKeyCredentialRpEntity::create(
        self::RP_NAME,
        self::RP_ID,
    );

    $userEntity = PublicKeyCredentialUserEntity::create(
        $user->getEmail(),
        $user->getId()->toRfc4122(),
        $user->getEmail(),
    );

    $challenge = random_bytes(32);

    // ✅ v4.x — constructeur direct, pas de méthodes statiques
    $options = PublicKeyCredentialCreationOptions::create(
        rp: $rp,
        user: $userEntity,
        challenge: $challenge,
        pubKeyCredParams: [
            PublicKeyCredentialParameters::create('public-key', -7),   
            PublicKeyCredentialParameters::create('public-key', -257), 
        ],
        timeout: 60000,
    );

    $this->requestStack->getSession()->set(
        'webauthn_registration',
        json_encode($options)
    );

    return json_decode(json_encode($options), true);
}
    

    public function verifyRegistration(string $responseJson, User $user): void
    {
        $session   = $this->requestStack->getSession();
        $optionRaw = $session->get('webauthn_registration');

        if (!$optionRaw) {
            throw new \RuntimeException('Session expirée. Recommencez l\'enregistrement.');
        }

        // ✅ Désérialiser les options depuis la session
        $options = $this->serializer->deserialize(
            $optionRaw,
            PublicKeyCredentialCreationOptions::class,
            'json'
        );

        // ✅ Désérialiser la réponse du client
        $publicKeyCredential = $this->serializer->deserialize(
            $responseJson,
            PublicKeyCredential::class,
            'json'
        );

        $response = $publicKeyCredential->getResponse();
        if (!$response instanceof AuthenticatorAttestationResponse) {
            throw new \RuntimeException('Réponse attestation invalide.');
        }

        // ✅ API v4 — on passe l'origin en string, pas la Request
        $credentialSource = $this->attestationValidator->check(
            $response,
            $options,
            self::ORIGIN,       // ← 'http://localhost:8080'
        );

        $this->credRepo->saveCredential($user, $credentialSource);
        $session->remove('webauthn_registration');
    }

    /* ── LOGIN OPTIONS ── */

    public function getLoginOptions(): array
    {
        $challenge = random_bytes(32);

        $options = PublicKeyCredentialRequestOptions::create(
            challenge: $challenge,
            rpId: self::RP_ID,
            timeout: 60000,
        );

        $this->requestStack->getSession()->set(
            'webauthn_login',
            json_encode($options)
        );

        return json_decode(json_encode($options), true);
    }

    /* ── LOGIN VERIFY ── */

    public function verifyLogin(string $responseJson): User
    {
        $session   = $this->requestStack->getSession();
        $optionRaw = $session->get('webauthn_login');

        if (!$optionRaw) {
            throw new \RuntimeException('Session expirée. Recommencez la connexion.');
        }

        $options = $this->serializer->deserialize(
            $optionRaw,
            PublicKeyCredentialRequestOptions::class,
            'json'
        );

        $publicKeyCredential = $this->serializer->deserialize(
            $responseJson,
            PublicKeyCredential::class,
            'json'
        );

        $response = $publicKeyCredential->getResponse();
        if (!$response instanceof AuthenticatorAssertionResponse) {
            throw new \RuntimeException('Réponse assertion invalide.');
        }

        // ✅ API v4
        $credentialSource = $this->assertionValidator->check(
            $publicKeyCredential->getRawId(),
            $response,
            $options,
            self::ORIGIN,       // ← 'http://localhost:8080'
            null,
        );

        $entity = $this->credRepo->findByCredentialId($credentialSource->getPublicKeyCredentialId());
        if (!$entity) {
            throw new \RuntimeException('Credential introuvable.');
        }

        $entity->touch();
        $this->em->flush();
        $session->remove('webauthn_login');

        return $entity->getUser();
    }
}