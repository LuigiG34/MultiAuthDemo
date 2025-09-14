<?php

namespace App\Service;

use App\DTO\GoogleUserData;
use Google\Client as GoogleClient;
use Google\Service\Oauth2;

final class GoogleOAuthService
{
    private GoogleClient $client;

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $redirectUri
    ) {
        $this->client = new GoogleClient();
        $this->client->setClientId($this->clientId);
        $this->client->setClientSecret($this->clientSecret);
        $this->client->setRedirectUri($this->redirectUri);
        $this->client->addScope(['email', 'profile']);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
    }

    public function getAuthorizationUrl(): string
    {
        return $this->client->createAuthUrl();
    }

    public function getUserFromCode(string $code): GoogleUserData
    {
        $token = $this->client->fetchAccessTokenWithAuthCode($code);
        
        if (isset($token['error'])) {
            throw new \RuntimeException(
                sprintf('Google OAuth error: %s', $token['error_description'] ?? $token['error'])
            );
        }

        $this->client->setAccessToken($token);
        
        $oauth2 = new Oauth2($this->client);
        $userInfo = $oauth2->userinfo->get();

        return new GoogleUserData(
            id: $userInfo->getId(),
            email: $userInfo->getEmail(),
            name: $userInfo->getName(),
            picture: $userInfo->getPicture(),
            verifiedEmail: $userInfo->getVerifiedEmail(),
            accessToken: $token['access_token'] ?? null,
            refreshToken: $token['refresh_token'] ?? null,
            expiresAt: isset($token['expires_in']) 
                ? new \DateTimeImmutable('+' . $token['expires_in'] . ' seconds')
                : null
        );
    }
}