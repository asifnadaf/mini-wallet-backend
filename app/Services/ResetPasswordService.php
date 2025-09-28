<?php

namespace App\Services;

use App\Models\User;
use App\Jobs\EmailResetPasswordSuccessJob;
use App\Strategies\Token\TokenSenderStrategy;
use App\Repositories\PasswordResetTokenRepository;
use Exception;

class ResetPasswordService
{
    protected TokenSenderStrategy $tokenSender;

    public function __construct(
        private PasswordResetTokenRepository $repository,
        array $params = []
    ) {
        $this->tokenSender = app(TokenSenderStrategy::class, $params);
    }

    /**
     * Reset the user password after verifying token.
     *
     * @throws Exception
     */
    public function reset(array $data): void
    {
        // Verify token using repository
        $this->repository->findValidToken($data['email'], $data['token'], 1800);

        // Delete used token
        $this->repository->deleteByToken($data['email'], $data['token']);

        // Update user password
        $user = User::where('email', $data['email'])->firstOrFail();
        $user->update(['password' => $data['password']]);

        // Send success email
        EmailResetPasswordSuccessJob::dispatch($user);
    }
}
