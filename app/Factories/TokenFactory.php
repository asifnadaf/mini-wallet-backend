<?php

namespace App\Factories;

class TokenFactory
{
    public function make(): string
    {
        return (string) random_int(100000, 999999);
    }
}
