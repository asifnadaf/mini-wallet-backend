<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;

class UserSeeder extends Seeder
{
    public function run()
    {

        $users = [
            [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'balance' => 1000.00,
            ],
            [
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
                'balance' => 500.00,
            ],
            [
                'name' => 'Bob Wilson',
                'email' => 'bob@example.com',
                'balance' => 750.00,
            ],
        ];

        $this->command->info("Creating users");

        foreach ($users as $userData) {
            $user = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => Hash::make($userData['email']),
                'email_verified_at' => Carbon::now(),
                'balance' => $userData['balance'],
            ]);

            $this->command->info("User '{$user->name}' created with email '{$user->email}' and balance {$user->balance}");
        }
    }
}
