<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Webauthn\PublicKeyCredentialSource;

#[ORM\Entity]
#[ORM\Table(name: 'webauthn_credential')]
class WebauthnCredential
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'webauthnCredentials')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(type: 'text')]
    private string $credentialData = '';

    #[ORM\Column(length: 255)]
    private string $name = 'Ma Passkey';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $lastUsedAt;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
        $this->lastUsedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid { return $this->id; }

    public function getUser(): User { return $this->user; }
    public function setUser(User $user): static { $this->user = $user; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getLastUsedAt(): \DateTimeImmutable { return $this->lastUsedAt; }

    public function touch(): void
    {
        $this->lastUsedAt = new \DateTimeImmutable();
    }

    public function getCredentialSource(): PublicKeyCredentialSource
    {
        $data = json_decode($this->credentialData, true);
        return PublicKeyCredentialSource::createFromArray($data);
    }

    public function setCredentialSource(PublicKeyCredentialSource $source): void
    {
        $this->credentialData = json_encode($source);
    }

    public function getCredentialData(): string { return $this->credentialData; }
    public function setCredentialData(string $data): static { $this->credentialData = $data; return $this; }
}
