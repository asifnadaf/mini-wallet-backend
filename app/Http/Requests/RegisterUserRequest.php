<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules;

class RegisterUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Set to true if you don't need authorization checks
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z\s]+$/'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:App\Models\User,email'],
            'password' => ['required', Rules\Password::defaults()],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'Name can only contain letters and spaces.',
        ];
    }
}
