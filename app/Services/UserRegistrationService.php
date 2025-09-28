<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Strategies\Token\TokenSenderStrategy;
use Illuminate\Support\Facades\Log;

class UserRegistrationService
{
    protected $tokenSender;

    public function __construct(TokenSenderStrategy $tokenSender)
    {
        $this->tokenSender = $tokenSender;
    }

    public function register(array $data): User
    {
        return DB::transaction(function () use ($data) {
            try {
                $user = User::create([
                    'name' => $data['name'],
                    'email' => strtolower($data['email']),
                    'password' => $data['password'],
                ]);

                $this->tokenSender->send($user);

                return $user;
            } catch (Exception $e) {
                // Log service-level errors but re-throw for controller to handle
                Log::error('UserRegistrationService failed: ' . $e->getMessage());
                throw $e;
            }
        });
    }
}
