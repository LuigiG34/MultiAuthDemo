<?php

namespace App\DTO;

final readonly class FacebookUserData
{
    public function __construct(
        public string $id,
        public ?string $email,
        public ?string $name,
        public ?string $picture,
        public ?string $accessToken,
    ) {}
}