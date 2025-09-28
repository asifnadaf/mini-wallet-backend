<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyEmailTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        // No special authorization checks needed
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'token.required' => 'OTP is required.',
            'token.integer' => 'OTP must be a valid number.',
        ];
    }
}
