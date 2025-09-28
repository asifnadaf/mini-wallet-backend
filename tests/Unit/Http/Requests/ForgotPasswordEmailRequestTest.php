<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\ForgotPasswordEmailRequest;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ForgotPasswordEmailRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorize_returns_true()
    {
        $request = new ForgotPasswordEmailRequest();
        $this->assertTrue($request->authorize());
    }

    public function test_rules_returns_correct_validation_rules()
    {
        $request = new ForgotPasswordEmailRequest();
        $rules = $request->rules();

        $this->assertArrayHasKey('email', $rules);
        $this->assertContains('required', $rules['email']);
        $this->assertContains('string', $rules['email']);
        $this->assertContains('email', $rules['email']);
        $this->assertContains('max:255', $rules['email']);
    }

    public function test_validation_passes_with_valid_data()
    {
        $request = new ForgotPasswordEmailRequest();
        $validator = validator([
            'email' => 'test@example.com',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_validation_fails_with_missing_email()
    {
        $request = new ForgotPasswordEmailRequest();
        $validator = validator([], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_validation_fails_with_invalid_email()
    {
        $request = new ForgotPasswordEmailRequest();
        $validator = validator([
            'email' => 'invalid-email',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_validation_fails_with_empty_email()
    {
        $request = new ForgotPasswordEmailRequest();
        $validator = validator([
            'email' => '',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_validation_fails_with_null_email()
    {
        $request = new ForgotPasswordEmailRequest();
        $validator = validator([
            'email' => null,
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_validation_fails_with_email_too_long()
    {
        $request = new ForgotPasswordEmailRequest();
        $validator = validator([
            'email' => str_repeat('a', 250) . '@example.com',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_validation_passes_with_max_length_email()
    {
        $request = new ForgotPasswordEmailRequest();
        $validator = validator([
            'email' => str_repeat('a', 240) . '@example.com',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }
}
