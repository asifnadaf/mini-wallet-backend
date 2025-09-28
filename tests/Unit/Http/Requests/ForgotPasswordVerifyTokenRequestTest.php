<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\ForgotPasswordVerifyTokenRequest;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ForgotPasswordVerifyTokenRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorize_returns_true()
    {
        $request = new ForgotPasswordVerifyTokenRequest();
        $this->assertTrue($request->authorize());
    }

    public function test_rules_returns_correct_validation_rules()
    {
        $request = new ForgotPasswordVerifyTokenRequest();
        $rules = $request->rules();

        $this->assertArrayHasKey('email', $rules);
        $this->assertContains('required', $rules['email']);
        $this->assertContains('string', $rules['email']);
        $this->assertContains('email', $rules['email']);
        $this->assertContains('max:255', $rules['email']);

        $this->assertArrayHasKey('token', $rules);
        $this->assertContains('required', $rules['token']);
    }

    public function test_validation_passes_with_valid_data()
    {
        $request = new ForgotPasswordVerifyTokenRequest();
        $validator = validator([
            'email' => 'test@example.com',
            'token' => '123456',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_validation_fails_with_missing_email()
    {
        $request = new ForgotPasswordVerifyTokenRequest();
        $validator = validator([
            'token' => '123456',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_validation_fails_with_missing_token()
    {
        $request = new ForgotPasswordVerifyTokenRequest();
        $validator = validator([
            'email' => 'test@example.com',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('token', $validator->errors()->toArray());
    }

    public function test_validation_fails_with_invalid_email()
    {
        $request = new ForgotPasswordVerifyTokenRequest();
        $validator = validator([
            'email' => 'invalid-email',
            'token' => '123456',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_validation_fails_with_empty_email()
    {
        $request = new ForgotPasswordVerifyTokenRequest();
        $validator = validator([
            'email' => '',
            'token' => '123456',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_validation_fails_with_empty_token()
    {
        $request = new ForgotPasswordVerifyTokenRequest();
        $validator = validator([
            'email' => 'test@example.com',
            'token' => '',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('token', $validator->errors()->toArray());
    }

    public function test_validation_fails_with_null_email()
    {
        $request = new ForgotPasswordVerifyTokenRequest();
        $validator = validator([
            'email' => null,
            'token' => '123456',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_validation_fails_with_null_token()
    {
        $request = new ForgotPasswordVerifyTokenRequest();
        $validator = validator([
            'email' => 'test@example.com',
            'token' => null,
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('token', $validator->errors()->toArray());
    }

    public function test_validation_fails_with_email_too_long()
    {
        $request = new ForgotPasswordVerifyTokenRequest();
        $validator = validator([
            'email' => str_repeat('a', 250) . '@example.com',
            'token' => '123456',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_validation_passes_with_max_length_email()
    {
        $request = new ForgotPasswordVerifyTokenRequest();
        $validator = validator([
            'email' => str_repeat('a', 240) . '@example.com',
            'token' => '123456',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }
}
