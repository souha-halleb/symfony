<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\WebauthnCredential;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Webauthn\PublicKeyCredentialSource;

/**
 * @extends ServiceEntityRepository<WebauthnCredential>
 */
class WebauthnCredentialRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WebauthnCredential::class);
    }

    public function saveCredential(User $user, PublicKeyCredentialSource $source, string $name = 'Ma Passkey'): WebauthnCredential
    {
        $credential = new WebauthnCredential();
        $credential->setUser($user);
        $credential->setCredentialSource($source);
        $credential->setName($name);

        $em = $this->getEntityManager();
        $em->persist($credential);
        $em->flush();

        return $credential;
    }

   
    public function findByCredentialId(string $credentialId): ?WebauthnCredential
    {
        $all = $this->findAll();
        foreach ($all as $credential) {
            $source = $credential->getCredentialSource();

            if ($source->getPublicKeyCredentialId() === $credentialId) {
                return $credential;
            }

            if (Base64UrlSafe::encodeUnpadded($source->getPublicKeyCredentialId()) === $credentialId) {
                return $credential;
            }
        }

        return null;
    }

    public function findAllByUser(User $user): array
    {
        return $this->findBy(['user' => $user]);
    }

    public function findAllSources(User $user): array
    {
        $credentials = $this->findAllByUser($user);
        return array_map(fn ($c) => $c->getCredentialSource(), $credentials);
    }
}