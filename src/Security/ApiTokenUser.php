<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

final class ApiTokenUser implements UserInterface
{
    public function getRoles(): array
    {
        return ['ROLE_API'];
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return 'api-token';
    }
}
