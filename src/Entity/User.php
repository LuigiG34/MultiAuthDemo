<?php

namespace App\Entity;

use App\Enum\AuthProvider;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    // Social-only accounts may have no usable email
    #[ORM\Column(type: 'string', length: 180, unique: true, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: 'json')]
    private array $roles = ['ROLE_USER'];

    // Only allowed when primaryProvider=LOCAL
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $password = null;

    // Single source of truth for auth technique
    #[ORM\Column(enumType: AuthProvider::class)]
    private AuthProvider $primaryProvider = AuthProvider::LOCAL;

    #[ORM\Column(type: 'string', length: 150, nullable: true)]
    private ?string $displayName = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $avatarUrl = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isVerified = false;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    // Exactly one identity when primaryProvider is social; null when LOCAL
    #[ORM\OneToOne(mappedBy: 'user', targetEntity: UserIdentity::class, cascade: ['persist', 'remove'])]
    private ?UserIdentity $identity = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // ---- Security ----
    public function getUserIdentifier(): string
    {
        return $this->email ?? (string) ($this->id ?? '');
    }

    /** @deprecated */
    public function getUsername(): string { return $this->getUserIdentifier(); }

    public function getRoles(): array { return array_values(array_unique($this->roles)); }
    public function setRoles(array $roles): void { $this->roles = $roles ?: ['ROLE_USER']; }
    public function eraseCredentials(): void {}

    // ---- Getters / Setters with invariants enforcing "one method only" ----

    public function getId(): ?int { return $this->id; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): void { $this->email = $email; }

    public function getPassword(): ?string { return $this->password; }
    public function setPassword(?string $hash): void
    {
        // Only local accounts may carry a password
        if ($hash !== null && $this->primaryProvider !== AuthProvider::LOCAL) {
            throw new \LogicException('Password is only allowed for LOCAL accounts.');
        }
        $this->password = $hash;
    }

    public function getPrimaryProvider(): AuthProvider { return $this->primaryProvider; }
    public function setPrimaryProvider(AuthProvider $p): void
    {
        // If switching to LOCAL, you cannot have a social identity
        if ($p === AuthProvider::LOCAL && $this->identity !== null) {
            throw new \LogicException('Cannot switch to LOCAL while a social identity is linked.');
        }
        // If switching to social, password must be null
        if ($p !== AuthProvider::LOCAL && $this->password !== null) {
            throw new \LogicException('Cannot switch to social while a password is set.');
        }
        $this->primaryProvider = $p;
    }

    public function getDisplayName(): ?string { return $this->displayName; }
    public function setDisplayName(?string $name): void { $this->displayName = $name; }

    public function getAvatarUrl(): ?string { return $this->avatarUrl; }
    public function setAvatarUrl(?string $url): void { $this->avatarUrl = $url; }

    public function isVerified(): bool { return $this->isVerified; }
    public function setIsVerified(bool $verified): void { $this->isVerified = $verified; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $active): void { $this->isActive = $active; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeImmutable $dt): void { $this->updatedAt = $dt; }

    public function getLastLoginAt(): ?\DateTimeImmutable { return $this->lastLoginAt; }
    public function setLastLoginAt(?\DateTimeImmutable $dt): void { $this->lastLoginAt = $dt; }

    public function getIdentity(): ?UserIdentity { return $this->identity; }

    public function setIdentity(?UserIdentity $identity): void
    {
        // Only social accounts can have an identity row
        if ($identity !== null && $this->primaryProvider === AuthProvider::LOCAL) {
            throw new \LogicException('LOCAL accounts cannot have a social identity.');
        }
        $this->identity = $identity;
        if ($identity && $identity->getUser() !== $this) {
            $identity->setUser($this);
        }
    }
}
