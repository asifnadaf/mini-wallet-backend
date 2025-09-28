<?php

namespace App\Services;

use Illuminate\Support\Facades\Hash;
use App\Jobs\EmailPasswordUpdatedJob;
use Illuminate\Validation\ValidationException;
use App\Models\User;

class ChangePasswordService
{
    /**
     * Change the authenticated user's password.
     *
     * @throws ValidationException
     */
    public function change(User $user, array $data): void
    {
        if (!Hash::check($data['old_password'], $user->password)) {
            throw ValidationException::withMessages([
                'old_password' => ['The old password is incorrect.'],
            ]);
        }

        $user->update([
            'password' => $data['new_password'],
        ]);

        EmailPasswordUpdatedJob::dispatch($user);
    }
}
