<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\PasswordResetTokenRepository;
use App\Strategies\Token\TokenSenderStrategy;
use Exception;

class ForgotPasswordService
{
    public function __construct(
        private PasswordResetTokenRepository $repository,
        private TokenSenderStrategy $tokenSender
    ) {}

    public function sendEmailToken(User $user): array
    {
        return $this->tokenSender->send($user);
    }

    /**
     * Verify token validity.
     *
     * @throws Exception
     */
    public function verifyToken(string $email, string $token): bool
    {
        $this->repository->findValidToken($email, $token, 1800);
        return true;
    }
}
