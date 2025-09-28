<?php

namespace App\Strategies\Token;

use App\Models\User;
use App\Services\PasswordTokenService;

class PasswordTokenStrategy implements TokenSenderStrategy
{
    public function __construct(
        private PasswordTokenService $passwordTokenService
    ) {}

    public function send(User $user): array
    {
        return $this->passwordTokenService->send($user);
    }
}
