<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\PasswordResetTokenRepository;
use App\Factories\TokenFactory;
use App\Jobs\ForgotPasswordEmailTokenJob;

class PasswordTokenService
{
    public function __construct(
        private PasswordResetTokenRepository $repository,
        private TokenFactory $factory
    ) {}

    public function send(User $user): array
    {
        // delete old tokens
        $this->repository->deleteByEmail($user->email);

        // generate new token
        $token = $this->factory->make();

        // persist token
        $this->repository->create($user->email, $token);

        // send email asynchronously
        dispatch(new ForgotPasswordEmailTokenJob($user, $token));

        return [
            'success' => true,
            'message' => "OTP sent to email {$user->email}. Please verify email.",
            'status_code' => 200,
        ];
    }
}
