<?php

namespace App\Entity;

use App\Enum\AuthProvider;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'user_identity')]
#[ORM\UniqueConstraint(name: 'uniq_provider_user', columns: ['provider', 'provider_user_id'])]
#[ORM\UniqueConstraint(name: 'uniq_identity_user', columns: ['user_id'])] // <= one identity per user
class UserIdentity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    // Owning side of the OneToOne
    #[ORM\OneToOne(inversedBy: 'identity')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    // Must be a social provider when used (LOCAL not expected here)
    #[ORM\Column(enumType: AuthProvider::class)]
    private AuthProvider $provider;

    // Stable provider-side user identifier (e.g., Google "sub")
    #[ORM\Column(name: 'provider_user_id', type: 'string', length: 191)]
    private string $providerUserId;

    // Optional metadata
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $providerEmail = null;

    #[ORM\Column(type: 'string', length: 512, nullable: true)]
    private ?string $accessToken = null;

    #[ORM\Column(type: 'string', length: 512, nullable: true)]
    private ?string $refreshToken = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $tokenExpiresAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $linkedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $profile = null;

    public function __construct()
    {
        $this->linkedAt = new \DateTimeImmutable();
    }

    // ---- Getters / Setters + guardrails ----

    public function getId(): ?int { return $this->id; }

    public function getUser(): User { return $this->user; }
    public function setUser(User $user): void
    {
        // Prevent attaching identity to a LOCAL account
        if ($user->getPrimaryProvider() === AuthProvider::LOCAL) {
            throw new \LogicException('Cannot attach social identity to a LOCAL user.');
        }
        $this->user = $user;
        if ($user->getIdentity() !== $this) {
            $user->setIdentity($this);
        }
    }

    public function getProvider(): AuthProvider { return $this->provider; }
    public function setProvider(AuthProvider $p): void
    {
        if ($p === AuthProvider::LOCAL) {
            throw new \LogicException('UserIdentity is only for social providers.');
        }
        $this->provider = $p;
    }

    public function getProviderUserId(): string { return $this->providerUserId; }
    public function setProviderUserId(string $id): void { $this->providerUserId = $id; }

    public function getProviderEmail(): ?string { return $this->providerEmail; }
    public function setProviderEmail(?string $email): void { $this->providerEmail = $email; }

    public function getAccessToken(): ?string { return $this->accessToken; }
    public function setAccessToken(?string $t): void { $this->accessToken = $t; }

    public function getRefreshToken(): ?string { return $this->refreshToken; }
    public function setRefreshToken(?string $t): void { $this->refreshToken = $t; }

    public function getTokenExpiresAt(): ?\DateTimeImmutable { return $this->tokenExpiresAt; }
    public function setTokenExpiresAt(?\DateTimeImmutable $dt): void { $this->tokenExpiresAt = $dt; }

    public function getLinkedAt(): \DateTimeImmutable { return $this->linkedAt; }
    public function setLinkedAt(\DateTimeImmutable $dt): void { $this->linkedAt = $dt; }

    public function getLastUsedAt(): ?\DateTimeImmutable { return $this->lastUsedAt; }
    public function setLastUsedAt(?\DateTimeImmutable $dt): void { $this->lastUsedAt = $dt; }

    public function getProfile(): ?array { return $this->profile; }
    public function setProfile(?array $profile): void { $this->profile = $profile; }
}
