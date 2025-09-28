<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\EmailVerificationTokenRepository;
use App\Factories\TokenFactory;
use App\Jobs\SendEmailTokenJob;

class EmailTokenService
{
    public function __construct(
        private EmailVerificationTokenRepository $repository,
        private TokenFactory $factory
    ) {}

    public function send(User $user): array
    {
        if ($user->email_verified_at !== null) {
            return [
                'success' => false,
                'message' => 'Email is already verified',
                'status_code' => 422,
            ];
        }

        $token = $this->factory->make();
        $this->repository->deleteByEmail($user->email);
        $this->repository->create($user->email, $token);

        dispatch(new SendEmailTokenJob($user->name, $user->email, $token));

        return [
            'success' => true,
            'message' => "OTP sent to email {$user->email}. Please verify email.",
            'status_code' => 200,
        ];
    }

    public function verify(User $user, array $data): array
    {
        if ($user->email_verified_at !== null) {
            return [
                'success' => false,
                'message' => 'Email is already verified',
                'status_code' => 422,
            ];
        }

        $record = $this->repository->findByEmailAndToken($user->email, $data['token']);

        if (!$record) {
            return [
                'success' => false,
                'message' => 'Invalid OTP',
                'status_code' => 400,
            ];
        }

        if ($this->repository->isExpired($record)) {
            return [
                'success' => false,
                'message' => 'Token expired',
                'status_code' => 400,
            ];
        }

        $user->email_verified_at = now();
        $user->save();

        $this->repository->deleteByEmail($user->email);

        return [
            'success' => true,
            'message' => "Email {$user->email} is verified",
            'status_code' => 200,
        ];
    }
}
