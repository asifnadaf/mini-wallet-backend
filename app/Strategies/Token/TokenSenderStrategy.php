<?php

namespace App\Strategies\Token;

use App\Models\User;

interface TokenSenderStrategy
{
    public function send(User $user): array;
}
