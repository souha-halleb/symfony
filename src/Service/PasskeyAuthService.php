<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\WebauthnCredentialRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\Bundle\Service\PublicKeyCredentialCreationOptionsFactory;
use Webauthn\Bundle\Service\PublicKeyCredentialRequestOptionsFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialSource;

class PasskeyAuthService
{
    public function __construct(
        private PublicKeyCredentialCreationOptionsFactory $creationFactory,
        private PublicKeyCredentialRequestOptionsFactory $requestFactory,
        private AuthenticatorAttestationResponseValidator $attestationValidator,
        private AuthenticatorAssertionResponseValidator $assertionValidator,
        private SerializerInterface $serializer,
        private RequestStack $requestStack,
        private WebauthnCredentialRepository $credRepo,
        private EntityManagerInterface $em,
    ) {}

    public function getRegistrationOptions(User $user): array
    {
        $options = $this->creationFactory->create('default', $user);
        $this->requestStack->getSession()->set('webauthn_registration', serialize($options));
        return json_decode(json_encode($options), true);
    }

    private function getRelyingPartyHost(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return $_ENV['APP_DOMAIN'] ?? 'localhost';
        }

        return $request->getHost();
    }

    public function verifyRegistration(string $responseJson, User $user): void
    {
        $session = $this->requestStack->getSession();
        $options = unserialize($session->get('webauthn_registration'));

        $publicKeyCredential = $this->serializer->deserialize(
            $responseJson,
            PublicKeyCredential::class,
            'json'
        );

        $response = $publicKeyCredential->getResponse();
        if (!$response instanceof AuthenticatorAttestationResponse) {
            throw new \RuntimeException('Réponse attestation invalide.');
        }

        $credentialSource = $this->attestationValidator->check($response, $options, $this->getRelyingPartyHost());
        $this->credRepo->saveCredential($user, $credentialSource);
        $session->remove('webauthn_registration');
    }

    public function getLoginOptions(): array
    {
        $options = $this->requestFactory->create('default');
        $this->requestStack->getSession()->set('webauthn_login', serialize($options));
        return json_decode(json_encode($options), true);
    }

    public function verifyLogin(string $responseJson): User
    {
        $session = $this->requestStack->getSession();
        $options = unserialize($session->get('webauthn_login'));

        $publicKeyCredential = $this->serializer->deserialize(
            $responseJson,
            PublicKeyCredential::class,
            'json'
        );

        $response = $publicKeyCredential->getResponse();
        if (!$response instanceof AuthenticatorAssertionResponse) {
            throw new \RuntimeException('Réponse assertion invalide.');
        }

        $credentialSource = $this->assertionValidator->check(
            $publicKeyCredential->getRawId(),
            $response,
            $options,
            $this->getRelyingPartyHost(),
            null
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
