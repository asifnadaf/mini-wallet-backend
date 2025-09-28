<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\VerifyEmailTokenRequest;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class VerifyEmailTokenRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorize_returns_true()
    {
        $request = new VerifyEmailTokenRequest();
        $this->assertTrue($request->authorize());
    }

    public function test_rules_returns_correct_validation_rules()
    {
        $request = new VerifyEmailTokenRequest();
        $rules = $request->rules();

        $this->assertArrayHasKey('token', $rules);
        $this->assertContains('required', $rules['token']);
        $this->assertContains('integer', $rules['token']);
    }

    public function test_messages_returns_custom_messages()
    {
        $request = new VerifyEmailTokenRequest();
        $messages = $request->messages();

        $this->assertArrayHasKey('token.required', $messages);
        $this->assertArrayHasKey('token.integer', $messages);
        $this->assertEquals('OTP is required.', $messages['token.required']);
        $this->assertEquals('OTP must be a valid number.', $messages['token.integer']);
    }

    public function test_validation_passes_with_valid_data()
    {
        $request = new VerifyEmailTokenRequest();
        $validator = validator([
            'token' => 123456,
        ], $request->rules(), $request->messages());

        $this->assertFalse($validator->fails());
    }

    public function test_validation_fails_with_missing_token()
    {
        $request = new VerifyEmailTokenRequest();
        $validator = validator([], $request->rules(), $request->messages());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('token', $validator->errors()->toArray());
    }

    public function test_validation_fails_with_non_integer_token()
    {
        $request = new VerifyEmailTokenRequest();
        $validator = validator([
            'token' => 'invalid-token',
        ], $request->rules(), $request->messages());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('token', $validator->errors()->toArray());
    }

    public function test_validation_fails_with_empty_token()
    {
        $request = new VerifyEmailTokenRequest();
        $validator = validator([
            'token' => '',
        ], $request->rules(), $request->messages());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('token', $validator->errors()->toArray());
    }

    public function test_validation_fails_with_null_token()
    {
        $request = new VerifyEmailTokenRequest();
        $validator = validator([
            'token' => null,
        ], $request->rules(), $request->messages());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('token', $validator->errors()->toArray());
    }

    public function test_validation_passes_with_zero_token()
    {
        $request = new VerifyEmailTokenRequest();
        $validator = validator([
            'token' => 0,
        ], $request->rules(), $request->messages());

        $this->assertFalse($validator->fails());
    }

    public function test_validation_passes_with_negative_token()
    {
        $request = new VerifyEmailTokenRequest();
        $validator = validator([
            'token' => -123456,
        ], $request->rules(), $request->messages());

        $this->assertFalse($validator->fails());
    }
}
