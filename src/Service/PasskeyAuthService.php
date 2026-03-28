<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\WebauthnCredentialRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\PublicKeyCredentialLoader;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialSource;

class PasskeyAuthService
{
    public function __construct(
        private AuthenticatorAttestationResponseValidator $attestationValidator,
        private AuthenticatorAssertionResponseValidator $assertionValidator,
        private PublicKeyCredentialLoader $credentialLoader,
        private RequestStack $requestStack,
        private WebauthnCredentialRepository $credRepo,
        private EntityManagerInterface $em,
        private string $rpId,
        private string $rpName,
    ) {}

    // ================= REGISTER =================

    public function getRegistrationOptions(User $user): array
    {
        $rp = PublicKeyCredentialRpEntity::create($this->rpName, $this->rpId);

        $userEntity = PublicKeyCredentialUserEntity::create(
            $user->getEmail(),
            (string) $user->getId(),
            $user->getEmail(),
        );

        $challenge = random_bytes(32);

        $credParams = [
            PublicKeyCredentialParameters::create('public-key', PublicKeyCredentialParameters::ALGORITHM_ES256),
            PublicKeyCredentialParameters::create('public-key', PublicKeyCredentialParameters::ALGORITHM_RS256),
        ];

        // Exclude existing credentials for this user
        $excludeCredentials = array_map(
            fn(PublicKeyCredentialSource $source) => PublicKeyCredentialDescriptor::create(
                'public-key',
                $source->getPublicKeyCredentialId()
            ),
            $this->credRepo->findAllSources($user)
        );

        $options = PublicKeyCredentialCreationOptions::create(
            $rp,
            $userEntity,
            $challenge,
            $credParams,
        )
            ->excludeCredentials(...$excludeCredentials)
            ->setAuthenticatorSelection(
                AuthenticatorSelectionCriteria::create(
                    null,
                    false,
                    AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED
                )
            )
            ->setTimeout(60000);

        $this->requestStack->getSession()->set(
            'webauthn_registration',
            serialize($options)
        );

        return json_decode(json_encode($options), true);
    }

    public function verifyRegistration(string $responseJson, User $user): void
    {
        $session = $this->requestStack->getSession();
        $options = unserialize($session->get('webauthn_registration'));

        $publicKeyCredential = $this->credentialLoader->load($responseJson);

        /** @var AuthenticatorAttestationResponse $response */
        $response = $publicKeyCredential->getResponse();

        if (!$response instanceof AuthenticatorAttestationResponse) {
            throw new \RuntimeException('Réponse d\'attestation invalide.');
        }

        $credentialSource = $this->attestationValidator->check(
            $response,
            $options,
            $this->rpId,
        );

        $this->credRepo->saveCredential($user, $credentialSource);

        $session->remove('webauthn_registration');
    }

    // ================= LOGIN =================

    public function getLoginOptions(): array
    {
        $challenge = random_bytes(32);

        $options = PublicKeyCredentialRequestOptions::create($challenge)
            ->setRpId($this->rpId)
            ->setTimeout(60000)
            ->setUserVerification(
                PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED
            );

        $this->requestStack->getSession()->set(
            'webauthn_login',
            serialize($options)
        );

        return json_decode(json_encode($options), true);
    }

    public function verifyLogin(string $responseJson): User
    {
        $session = $this->requestStack->getSession();
        $options = unserialize($session->get('webauthn_login'));

        $publicKeyCredential = $this->credentialLoader->load($responseJson);

        /** @var AuthenticatorAssertionResponse $response */
        $response = $publicKeyCredential->getResponse();

        if (!$response instanceof AuthenticatorAssertionResponse) {
            throw new \RuntimeException('Réponse d\'assertion invalide.');
        }

        $credentialSource = $this->assertionValidator->check(
            $publicKeyCredential->getRawId(),
            $response,
            $options,
            $this->rpId,
            null,
        );

        $entity = $this->credRepo->findByCredentialId(
            $credentialSource->getPublicKeyCredentialId()
        );

        if (!$entity) {
            throw new \RuntimeException('Credential introuvable.');
        }

        $entity->touch();
        $this->em->flush();

        $session->remove('webauthn_login');

        return $entity->getUser();
    }
}