<?php

namespace App\Strategies\Token;

use App\Models\User;
use App\Services\EmailTokenService;

class EmailTokenStrategy implements TokenSenderStrategy
{
    public function __construct(
        private EmailTokenService $emailTokenService
    ) {}

    public function send(User $user): array
    {
        return $this->emailTokenService->send($user);
    }
}
