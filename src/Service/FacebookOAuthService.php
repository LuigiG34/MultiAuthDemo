<?php

namespace App\Service;

use App\DTO\FacebookUserData;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class FacebookOAuthService
{
    public function __construct(
        private readonly string $appId,
        private readonly string $appSecret,
        private readonly string $redirectUri,
        private readonly RequestStack $requestStack,
        private readonly HttpClientInterface $http,
    ) {}

    public function getAuthorizationUrl(): string
    {
        $params = http_build_query([
            'client_id'     => $this->appId,
            'redirect_uri'  => $this->redirectUri,
            'response_type' => 'code',
            'scope'         => 'email',
            'state'         => bin2hex(random_bytes(16)),
        ]);
        return 'https://www.facebook.com/v23.0/dialog/oauth?'.$params;
    }

    public function getUserFromCode(string $code): FacebookUserData
    {
        $token = $this->http->request('GET', 'https://graph.facebook.com/v23.0/oauth/access_token', [
            'query' => [
                'client_id'     => $this->appId,
                'client_secret' => $this->appSecret,
                'redirect_uri'  => $this->redirectUri,
                'code'          => $code,
            ],
        ])->toArray(false);

        if (isset($token['error'])) {
            throw new \RuntimeException('Facebook token error: '.$token['error']['message']);
        }

        $accessToken = $token['access_token'] ?? null;
        if (!$accessToken) {
            throw new \RuntimeException('No access token returned by Facebook.');
        }

        $appsecretProof = hash_hmac('sha256', $accessToken, $this->appSecret);

        $me = $this->http->request('GET', 'https://graph.facebook.com/v23.0/me', [
            'query' => [
                'fields'          => 'id,name,email,picture.type(large)',
                'access_token'    => $accessToken,
                'appsecret_proof' => $appsecretProof,
            ],
        ])->toArray(false);

        if (isset($me['error'])) {
            throw new \RuntimeException('Graph error: '.$me['error']['message']);
        }

        $pictureUrl = $me['picture']['data']['url'] ?? null;

        return new FacebookUserData(
            id: (string) $me['id'],
            email: $me['email'] ?? null,
            name: $me['name'] ?? null,
            picture: $pictureUrl,
            accessToken: $accessToken
        );
    }
}
