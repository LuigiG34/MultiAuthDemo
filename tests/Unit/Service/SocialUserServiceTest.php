<?php

namespace App\Tests\Service;

use App\DTO\FacebookUserData;
use App\DTO\GoogleUserData;
use App\Enum\AuthProvider;
use App\Entity\User;
use App\Entity\UserIdentity;
use App\Repository\UserRepository;
use App\Service\SocialUserService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class SocialUserServiceTest extends TestCase
{
    private function emWithIdentityRepo(?UserIdentity $found): EntityManagerInterface
    {
        $identityRepo = new class($found) {
            public function __construct(private ?UserIdentity $found) {}
            public function findOneBy(array $criteria) { return $this->found; }
        };

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnMap([
            [UserIdentity::class, $identityRepo],
        ]);
        // no-op
        $em->method('persist');
        $em->method('flush');

        return $em;
    }

    public function testFindOrCreateGoogleUserReturnsExistingAndUpdates(): void
    {
        // Existing identity path
        $user = new User();
        $user->setPrimaryProvider(AuthProvider::GOOGLE);
        $identity = new UserIdentity();
        $identity->setProvider(AuthProvider::GOOGLE);
        $identity->setProviderUserId('gid-1');
        $identity->setUser($user);

        $em = $this->emWithIdentityRepo($identity);

        $users = $this->createMock(UserRepository::class);
        $svc = new SocialUserService($em, $users);

        $dto = new GoogleUserData(
            id: 'gid-1',
            email: 'luigi@example.com',
            name: 'Luigi',
            picture: 'https://pic',
            verifiedEmail: true,
            accessToken: 'AT',
            refreshToken: 'RT',
            expiresAt: new DateTimeImmutable('+3600 seconds')
        );

        $result = $svc->findOrCreateGoogleUser($dto);

        $this->assertSame($user, $result);
        $this->assertSame('Luigi', $user->getDisplayName());
        $this->assertSame('https://pic', $user->getAvatarUrl());
        $this->assertSame('AT', $identity->getAccessToken());
        $this->assertSame('RT', $identity->getRefreshToken());
        $this->assertInstanceOf(DateTimeImmutable::class, $identity->getLastUsedAt());
    }

    public function testFindOrCreateGoogleUserConflictsWithLocalThrows(): void
    {
        $em = $this->emWithIdentityRepo(null);
        $existing = new User();
        $existing->setPrimaryProvider(AuthProvider::LOCAL);

        $users = $this->createMock(UserRepository::class);
        $users->method('findOneBy')->willReturn($existing);

        $svc = new SocialUserService($em, $users);

        $dto = new GoogleUserData('gid', 'luigi@example.com', 'Luigi', null, true, 'AT', 'RT', null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Please log in with your password first');
        $svc->findOrCreateGoogleUser($dto);
    }

    public function testFindOrCreateGoogleUserConflictsWithOtherSocialThrows(): void
    {
        $em = $this->emWithIdentityRepo(null);
        $existing = new User();
        $existing->setPrimaryProvider(AuthProvider::FACEBOOK);

        $users = $this->createMock(UserRepository::class);
        $users->method('findOneBy')->willReturn($existing);

        $svc = new SocialUserService($em, $users);

        $dto = new GoogleUserData('gid', 'luigi@example.com', 'Luigi', null, true, 'AT', null, null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already associated with a different social login provider');
        $svc->findOrCreateGoogleUser($dto);
    }

    public function testFindOrCreateGoogleUserCreatesNew(): void
    {
        $em = $this->emWithIdentityRepo(null);
        $users = $this->createMock(UserRepository::class);
        $users->method('findOneBy')->willReturn(null);

        $svc = new SocialUserService($em, $users);
        $dto = new GoogleUserData('gid', 'luigi@example.com', 'Luigi', 'https://pic', true, 'AT', 'RT', null);

        $created = $svc->findOrCreateGoogleUser($dto);

        $this->assertSame('luigi@example.com', $created->getEmail());
        $this->assertSame(AuthProvider::GOOGLE, $created->getPrimaryProvider());
        $this->assertInstanceOf(UserIdentity::class, $created->getIdentity());
        $this->assertSame('gid', $created->getIdentity()->getProviderUserId());
    }

    public function testFindOrCreateFacebookUserMirrorsGoogleLogic(): void
    {
        // New user creation path via Facebook
        $em = $this->emWithIdentityRepo(null);
        $users = $this->createMock(UserRepository::class);
        $users->method('findOneBy')->willReturn(null);

        $svc = new SocialUserService($em, $users);
        $dto = new FacebookUserData('fb-1', 'luigi@example.com', 'Luigi', 'https://pic', 'AT');

        $created = $svc->findOrCreateFacebookUser($dto);

        $this->assertSame(AuthProvider::FACEBOOK, $created->getPrimaryProvider());
        $this->assertTrue($created->isVerified()); // service sets true
        $this->assertSame('fb-1', $created->getIdentity()->getProviderUserId());
        $this->assertSame('AT', $created->getIdentity()->getAccessToken());
    }
}
