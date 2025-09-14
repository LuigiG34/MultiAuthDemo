<?php

namespace App\Service;

use App\DTO\GoogleUserData;
use App\DTO\FacebookUserData;
use App\Entity\User;
use App\Entity\UserIdentity;
use App\Enum\AuthProvider;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

final class SocialUserService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository
    ) {}

    public function findOrCreateGoogleUser(GoogleUserData $googleUser): User
    {
        $identity = $this->entityManager->getRepository(UserIdentity::class)->findOneBy([
            'provider' => AuthProvider::GOOGLE,
            'providerUserId' => $googleUser->id
        ]);

        if ($identity) {
            $user = $identity->getUser();
            $this->updateGoogleUser($user, $identity, $googleUser);
            return $user;
        }

        $existingUser = null;
        if ($googleUser->email) {
            $existingUser = $this->userRepository->findOneBy(['email' => $googleUser->email]);
        }

        if ($existingUser) {
            if ($existingUser->getPrimaryProvider() === AuthProvider::LOCAL) {
                throw new \RuntimeException(
                    'An account with this email already exists. Please log in with your password first, then link your Google account from your profile.'
                );
            }
            
            throw new \RuntimeException(
                'This email is already associated with a different social login provider.'
            );
        }

        return $this->createGoogleUser($googleUser);
    }

    private function createGoogleUser(GoogleUserData $googleUser): User
    {
        $user = new User();
        $user->setEmail($googleUser->email);
        $user->setDisplayName($googleUser->name);
        $user->setAvatarUrl($googleUser->picture);
        $user->setPrimaryProvider(AuthProvider::GOOGLE);
        $user->setIsVerified($googleUser->verifiedEmail);
        $user->setRoles(['ROLE_USER']);
        $user->setLastLoginAt(new \DateTimeImmutable());
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $identity = new UserIdentity();
        $identity->setProvider(AuthProvider::GOOGLE);
        $identity->setProviderUserId($googleUser->id);
        $identity->setProviderEmail($googleUser->email);
        $identity->setAccessToken($googleUser->accessToken);
        $identity->setRefreshToken($googleUser->refreshToken);
        $identity->setTokenExpiresAt($googleUser->expiresAt);
        $identity->setLastUsedAt(new \DateTimeImmutable());
        $identity->setProfile([
            'name' => $googleUser->name,
            'picture' => $googleUser->picture,
            'verified_email' => $googleUser->verifiedEmail
        ]);
        $identity->setUser($user);
        $this->entityManager->persist($identity);
        $this->entityManager->flush();

        $user->setIdentity($identity);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function updateGoogleUser(User $user, UserIdentity $identity, GoogleUserData $googleUser): void
    {
        $user->setLastLoginAt(new \DateTimeImmutable());
        
        if ($user->getDisplayName() !== $googleUser->name) {
            $user->setDisplayName($googleUser->name);
        }
        
        if ($user->getAvatarUrl() !== $googleUser->picture) {
            $user->setAvatarUrl($googleUser->picture);
        }

        $identity->setAccessToken($googleUser->accessToken);
        if ($googleUser->refreshToken) {
            $identity->setRefreshToken($googleUser->refreshToken);
        }
        $identity->setTokenExpiresAt($googleUser->expiresAt);
        $identity->setLastUsedAt(new \DateTimeImmutable());
        $identity->setProfile([
            'name' => $googleUser->name,
            'picture' => $googleUser->picture,
            'verified_email' => $googleUser->verifiedEmail
        ]);

        $this->entityManager->flush();
    }





    public function findOrCreateFacebookUser(FacebookUserData $facebookUser): User
    {
        $identity = $this->entityManager->getRepository(UserIdentity::class)->findOneBy([
            'provider' => AuthProvider::FACEBOOK,
            'providerUserId' => $facebookUser->id
        ]);

        if ($identity) {
            $user = $identity->getUser();
            $this->updateFacebookUser($user, $identity, $facebookUser);
            return $user;
        }

        $existingUser = null;
        if ($facebookUser->email) {
            $existingUser = $this->userRepository->findOneBy(['email' => $facebookUser->email]);
        }

        if ($existingUser) {
            if ($existingUser->getPrimaryProvider() === AuthProvider::LOCAL) {
                throw new \RuntimeException(
                    'An account with this email already exists. Please log in with your password first.'
                );
            }
            
            throw new \RuntimeException(
                'This email is already associated with a different social login provider.'
            );
        }

        return $this->createFacebookUser($facebookUser);
    }

    private function createFacebookUser(FacebookUserData $facebookUser): User
    {
        $user = new User();
        $user->setEmail($facebookUser->email);
        $user->setDisplayName($facebookUser->name);
        $user->setAvatarUrl($facebookUser->picture);
        $user->setPrimaryProvider(AuthProvider::FACEBOOK);
        $user->setIsVerified(true);
        $user->setRoles(['ROLE_USER']);
        $user->setLastLoginAt(new \DateTimeImmutable());
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $identity = new UserIdentity();
        $identity->setProvider(AuthProvider::FACEBOOK);
        $identity->setProviderUserId($facebookUser->id);
        $identity->setProviderEmail($facebookUser->email);
        $identity->setAccessToken($facebookUser->accessToken);
        $identity->setLastUsedAt(new \DateTimeImmutable());
        $identity->setProfile([
            'name' => $facebookUser->name,
            'picture' => $facebookUser->picture,
        ]);
        $identity->setUser($user);
        $this->entityManager->persist($identity);
        $this->entityManager->flush();

        $user->setIdentity($identity);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function updateFacebookUser(User $user, UserIdentity $identity, FacebookUserData $facebookUser): void
    {
        $user->setLastLoginAt(new \DateTimeImmutable());
        
        if ($user->getDisplayName() !== $facebookUser->name) {
            $user->setDisplayName($facebookUser->name);
        }
        
        if ($user->getAvatarUrl() !== $facebookUser->picture) {
            $user->setAvatarUrl($facebookUser->picture);
        }

        $identity->setAccessToken($facebookUser->accessToken);
        $identity->setLastUsedAt(new \DateTimeImmutable());
        $identity->setProfile([
            'name' => $facebookUser->name,
            'picture' => $facebookUser->picture,
        ]);

        $this->entityManager->flush();
    }
}