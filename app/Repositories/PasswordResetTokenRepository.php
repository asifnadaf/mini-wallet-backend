<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class PasswordResetTokenRepository
{
    public function deleteByEmail(string $email): void
    {
        DB::table('password_reset_tokens')
            ->where('email', $email)
            ->delete();
    }

    public function create(string $email, string $token): bool
    {
        return DB::table('password_reset_tokens')->insert([
            'email'      => $email,
            'token'      => $token,
            'created_at' => now(),
        ]);
    }

    public function findValidToken(string $email, string $token, int $ttlSeconds = 1800): object
    {
        $record = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->where('token', $token)
            ->first();

        if (!$record) {
            throw new Exception('Invalid token');
        }

        if (Carbon::now()->diffInSeconds($record->created_at) > $ttlSeconds) {
            throw new Exception('Token expired');
        }

        return $record;
    }

    public function deleteByToken(string $email, string $token): void
    {
        DB::table('password_reset_tokens')
            ->where('email', $email)
            ->where('token', $token)
            ->delete();
    }
}
