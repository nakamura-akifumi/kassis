<?php

namespace App\Security;

use App\Repository\ApiTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class ApiTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private ApiTokenRepository $apiTokenRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        $path = $request->getPathInfo();
        if (!str_starts_with($path, '/api')) {
            return false;
        }
        if ($path === '/api/openapi.yaml') {
            return false;
        }
        return true;
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $authHeader = $request->headers->get('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            throw new CustomUserMessageAuthenticationException('Missing bearer token.');
        }

        $token = trim(substr($authHeader, 7));
        if ($token === '') {
            throw new CustomUserMessageAuthenticationException('Missing bearer token.');
        }

        $hash = hash('sha256', $token);
        $apiToken = $this->apiTokenRepository->findOneBy([
            'tokenHash' => $hash,
            'enabled' => true,
        ]);

        if ($apiToken === null) {
            throw new CustomUserMessageAuthenticationException('Invalid bearer token.');
        }
        $expiresAt = $apiToken->getExpiresAt();
        if ($expiresAt !== null && $expiresAt <= new \DateTimeImmutable()) {
            throw new CustomUserMessageAuthenticationException('Bearer token has expired.');
        }

        $apiToken->setLastUsedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return new SelfValidatingPassport(new UserBadge('api-token', static fn () => new ApiTokenUser()));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?\Symfony\Component\HttpFoundation\Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?\Symfony\Component\HttpFoundation\Response
    {
        return new \Symfony\Component\HttpFoundation\JsonResponse([
            'error' => $exception->getMessageKey(),
        ], 401);
    }

    public function createAuthenticatedToken(\Symfony\Component\Security\Http\Authenticator\Passport\Passport $passport, string $firewallName): TokenInterface
    {
        return new UsernamePasswordToken($passport->getUser(), $firewallName, $passport->getUser()->getRoles());
    }
}
