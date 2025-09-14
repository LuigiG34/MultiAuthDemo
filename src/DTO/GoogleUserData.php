<?php

namespace App\DTO;

final readonly class GoogleUserData
{
    public function __construct(
        public string $id,
        public ?string $email,
        public ?string $name,
        public ?string $picture,
        public bool $verifiedEmail,
        public ?string $accessToken,
        public ?string $refreshToken,
        public ?\DateTimeImmutable $expiresAt,
    ) {}
}