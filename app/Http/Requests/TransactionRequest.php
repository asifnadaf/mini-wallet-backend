<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransactionRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'receiver_id' => [
                'required',
                'exists:users,id',
                Rule::notIn([$this->user()->id])
            ],
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
                'max:999999999.99'
            ],
        ];
    }

    public function messages()
    {
        return [
            'receiver_id.exists' => 'The selected receiver does not exist.',
            'receiver_id.not_in' => 'You cannot send money to yourself.',
            'amount.min' => 'The amount must be at least 0.01.',
            'amount.max' => 'The amount may not be greater than 999,999,999.99.',
        ];
    }
}
