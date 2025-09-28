<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EmailVerificationTokenRepository
{
    private string $table = 'email_verification_tokens';
    private int $expirySeconds = 1800; // 30 minutes

    public function deleteByEmail(string $email): void
    {
        DB::table($this->table)->where('email', $email)->delete();
    }

    public function create(string $email, string $token): bool
    {
        return DB::table($this->table)->insert([
            'email' => $email,
            'token' => $token,
            'created_at' => now(),
        ]);
    }

    public function findByEmailAndToken(string $email, string $token): ?object
    {
        return DB::table($this->table)
            ->where('email', $email)
            ->where('token', $token)
            ->first();
    }

    public function isExpired(object $record): bool
    {
        return Carbon::now()->diffInSeconds($record->created_at) > $this->expirySeconds;
    }
}
