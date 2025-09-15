<?php

namespace App\Tests\Service;

use App\DTO\FacebookUserData;
use App\Service\FacebookOAuthService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class FacebookOAuthServiceTest extends TestCase
{
    private function mkResponse(array $payload): ResponseInterface
    {
        return new class($payload) implements ResponseInterface {
            public function __construct(private array $payload) {}
            public function getStatusCode(): int { return 200; }
            public function getHeaders(bool $throw = true): array { return []; }
            public function getContent(bool $throw = true): string { return json_encode($this->payload); }
            public function toArray(bool $throw = true): array { return $this->payload; }
            public function cancel(): void {}
            public function getInfo(string $type = null): mixed { return null; }
        };
    }

    public function testGetAuthorizationUrlContainsExpectedParams(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $svc = new FacebookOAuthService('APP_ID', 'SECRET', 'https://example.com/cb', new RequestStack(), $http);

        $url = $svc->getAuthorizationUrl();

        $this->assertStringStartsWith('https://www.facebook.com/v23.0/dialog/oauth?', $url);
        parse_str(parse_url($url, PHP_URL_QUERY), $q);
        $this->assertSame('APP_ID', $q['client_id']);
        $this->assertSame('https://example.com/cb', $q['redirect_uri']);
        $this->assertSame('code', $q['response_type']);
        $this->assertSame('email', $q['scope']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $q['state']);
    }

    public function testGetUserFromCodeSuccess(): void
    {
        $http = $this->createMock(HttpClientInterface::class);

        $http->expects($this->at(0))
            ->method('request')
            ->with('GET', 'https://graph.facebook.com/v23.0/oauth/access_token', $this->callback(function ($opt) {
                return ($opt['query']['code'] ?? null) === 'THE_CODE';
            }))
            ->willReturn($this->mkResponse(['access_token' => 'AT']));

        $http->expects($this->at(1))
            ->method('request')
            ->with('GET', 'https://graph.facebook.com/v23.0/me', $this->callback(function ($opt) {
                return ($opt['query']['access_token'] ?? null) === 'AT'
                    && ($opt['query']['fields'] ?? null) === 'id,name,email,picture.type(large)';
            }))
            ->willReturn($this->mkResponse([
                'id' => '123',
                'name' => 'Luigi',
                'email' => 'luigi@example.com',
                'picture' => ['data' => ['url' => 'https://img.example/pic.jpg']],
            ]));

        $svc = new FacebookOAuthService('APP_ID', 'SECRET', 'https://example.com/cb', new RequestStack(), $http);
        $dto = $svc->getUserFromCode('THE_CODE');

        $this->assertInstanceOf(FacebookUserData::class, $dto);
        $this->assertSame('123', $dto->id);
        $this->assertSame('luigi@example.com', $dto->email);
        $this->assertSame('Luigi', $dto->name);
        $this->assertSame('https://img.example/pic.jpg', $dto->picture);
        $this->assertSame('AT', $dto->accessToken);
    }

    public function testGetUserFromCodeTokenErrorThrows(): void
    {
        $http = $this->createMock(HttpClientInterface::class);

        $http->method('request')->willReturn(
            $this->mkResponse(['error' => ['message' => 'Invalid code']])
        );

        $svc = new FacebookOAuthService('APP_ID', 'SECRET', 'https://example.com/cb', new RequestStack(), $http);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Facebook token error: Invalid code');
        $svc->getUserFromCode('BAD');
    }

    public function testGetUserFromCodeNoAccessTokenThrows(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->method('request')->willReturn($this->mkResponse([]));

        $svc = new FacebookOAuthService('APP_ID', 'SECRET', 'https://example.com/cb', new RequestStack(), $http);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No access token returned by Facebook.');
        $svc->getUserFromCode('CODE');
    }
}
