<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\WebauthnCredentialRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\Server;

class PasskeyAuthService
{
    private string $rpId;
    private string $rpName;

    public function __construct(
        private Server $webauthnServer,
        private RequestStack $requestStack,
        private WebauthnCredentialRepository $credRepo,
        string $appDomain,
        string $rpName
    ) {
        $this->rpId = $appDomain;
        $this->rpName = $rpName;
    }

    /**
     * Génère les options pour l'enregistrement d'une Passkey.
     */
    public function getRegistrationOptions(User $user): array
    {
        $userEntity = new PublicKeyCredentialUserEntity(
            $user->getEmail(),
            $user->getId()->toBinary(),
            $user->getEmail()
        );

        $options = $this->webauthnServer->generatePublicKeyCredentialCreationOptions(
            $userEntity,
            PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            $this->getExcludedCredentials($user)
        );

        $session = $this->requestStack->getSession();
        $session->set('webauthn_registration', serialize($options));

        return json_decode(json_encode($options), true);
    }

    /**
     * Valide la réponse d'enregistrement et sauvegarde la credential.
     */
    public function verifyRegistration(string $responseJson, User $user): void
    {
        $session = $this->requestStack->getSession();
        $optionsSerialized = $session->get('webauthn_registration');

        if (!$optionsSerialized) {
            throw new \RuntimeException('Session expirée. Recommencez l\'enregistrement.');
        }

        $options = unserialize($optionsSerialized);
        $userEntity = new PublicKeyCredentialUserEntity(
            $user->getEmail(),
            $user->getId()->toBinary(),
            $user->getEmail()
        );

        $credential = $this->webauthnServer->loadAndCheckAttestationResponse(
            $responseJson,
            $options,
            $userEntity
        );

        $this->credRepo->saveCredential($user, $credential);
        $session->remove('webauthn_registration');
    }

    /**
     * Génère les options pour la connexion par Passkey.
     */
    public function getLoginOptions(): array
    {
        $options = $this->webauthnServer->generatePublicKeyCredentialRequestOptions(
            PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED
        );

        $session = $this->requestStack->getSession();
        $session->set('webauthn_login', serialize($options));

        return json_decode(json_encode($options), true);
    }

    /**
     * Valide la réponse de connexion et retourne l'utilisateur authentifié.
     */
    public function verifyLogin(string $responseJson): User
    {
        $session = $this->requestStack->getSession();
        $optionsSerialized = $session->get('webauthn_login');

        if (!$optionsSerialized) {
            throw new \RuntimeException('Session expirée. Recommencez la connexion.');
        }

        $options = unserialize($optionsSerialized);

        $credential = $this->webauthnServer->loadAndCheckAssertionResponse(
            $responseJson,
            $options,
            null,
            null
        );

        $entity = $this->credRepo->findByCredentialId(
            $credential->getPublicKeyCredentialId()
        );

        if (!$entity) {
            throw new \RuntimeException('Credential introuvable.');
        }

        $entity->touch();
        $this->getEntityManager()->flush();

        $session->remove('webauthn_login');

        return $entity->getUser();
    }

    /**
     * Retourne les credentials déjà enregistrés pour éviter les doublons.
     */
    private function getExcludedCredentials(User $user): array
    {
        return array_map(
            fn($source) => $source->getPublicKeyCredentialDescriptor(),
            $this->credRepo->findAllSources($user)
        );
    }

    private function getEntityManager(): \Doctrine\ORM\EntityManagerInterface
    {
        return $this->credRepo->getEntityManager();
    }
}
