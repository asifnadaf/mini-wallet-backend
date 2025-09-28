<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\LoginRequest;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LoginRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorize_returns_true()
    {
        $request = new LoginRequest();
        $this->assertTrue($request->authorize());
    }

    public function test_rules_returns_correct_validation_rules()
    {
        $request = new LoginRequest();
        $rules = $request->rules();

        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayHasKey('password', $rules);
        $this->assertContains('required', $rules['email']);
        $this->assertContains('email', $rules['email']);
        $this->assertContains('required', $rules['password']);
        $this->assertContains('string', $rules['password']);
    }

    public function test_messages_returns_custom_messages()
    {
        $request = new LoginRequest();
        $messages = $request->messages();

        $this->assertArrayHasKey('email.required', $messages);
        $this->assertArrayHasKey('email.email', $messages);
        $this->assertArrayHasKey('password.required', $messages);
        $this->assertEquals('Email address is required.', $messages['email.required']);
        $this->assertEquals('Please provide a valid email address.', $messages['email.email']);
        $this->assertEquals('Password is required.', $messages['password.required']);
    }

    public function test_validation_passes_with_valid_data()
    {
        $request = new LoginRequest();
        $validator = validator([
            'email' => 'john@example.com',
            'password' => 'password123',
        ], $request->rules(), $request->messages());

        $this->assertFalse($validator->fails());
    }

    public function test_validation_fails_with_missing_email()
    {
        $request = new LoginRequest();
        $validator = validator([
            'password' => 'password123',
        ], $request->rules(), $request->messages());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_validation_fails_with_invalid_email()
    {
        $request = new LoginRequest();
        $validator = validator([
            'email' => 'invalid-email',
            'password' => 'password123',
        ], $request->rules(), $request->messages());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_validation_fails_with_missing_password()
    {
        $request = new LoginRequest();
        $validator = validator([
            'email' => 'john@example.com',
        ], $request->rules(), $request->messages());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    public function test_validation_fails_with_empty_email()
    {
        $request = new LoginRequest();
        $validator = validator([
            'email' => '',
            'password' => 'password123',
        ], $request->rules(), $request->messages());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_validation_fails_with_empty_password()
    {
        $request = new LoginRequest();
        $validator = validator([
            'email' => 'john@example.com',
            'password' => '',
        ], $request->rules(), $request->messages());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }
}
