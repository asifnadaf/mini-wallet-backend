<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\RegisterUserRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class RegisterUserRequestTest extends TestCase
{
    public function test_authorize_returns_true()
    {
        $request = new RegisterUserRequest();
        $this->assertTrue($request->authorize());
    }

    public function test_rules_returns_correct_validation_rules()
    {
        $request = new RegisterUserRequest();
        $rules = $request->rules();

        $this->assertArrayHasKey('name', $rules);
        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayHasKey('password', $rules);

        $this->assertContains('required', $rules['name']);
        $this->assertContains('string', $rules['name']);
        $this->assertContains('max:255', $rules['name']);
        $this->assertContains('regex:/^[a-zA-Z\s]+$/', $rules['name']);

        $this->assertContains('required', $rules['email']);
        $this->assertContains('string', $rules['email']);
        $this->assertContains('email', $rules['email']);
        $this->assertContains('max:255', $rules['email']);
        $this->assertContains('unique:App\Models\User,email', $rules['email']);

        $this->assertContains('required', $rules['password']);
    }

    public function test_messages_returns_custom_messages()
    {
        $request = new RegisterUserRequest();
        $messages = $request->messages();

        $this->assertArrayHasKey('name.regex', $messages);
        $this->assertEquals('Name can only contain letters and spaces.', $messages['name.regex']);
    }

    public function test_validation_passes_with_valid_data()
    {
        $request = new RegisterUserRequest();
        $rules = $request->rules();

        $validData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
        ];

        $validator = Validator::make($validData, $rules);
        $this->assertTrue($validator->passes());
    }

    public function test_validation_fails_with_invalid_name()
    {
        $request = new RegisterUserRequest();
        $rules = $request->rules();

        $invalidData = [
            'name' => 'John123', // Contains numbers
            'email' => 'john@example.com',
            'password' => 'password123',
        ];

        $validator = Validator::make($invalidData, $rules);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_fails_with_invalid_email()
    {
        $request = new RegisterUserRequest();
        $rules = $request->rules();

        $invalidData = [
            'name' => 'John Doe',
            'email' => 'invalid-email',
            'password' => 'password123',
        ];

        $validator = Validator::make($invalidData, $rules);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_validation_fails_with_missing_required_fields()
    {
        $request = new RegisterUserRequest();
        $rules = $request->rules();

        $invalidData = [
            'name' => 'John Doe',
            // Missing email and password
        ];

        $validator = Validator::make($invalidData, $rules);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    public function test_validation_fails_with_empty_name()
    {
        $request = new RegisterUserRequest();
        $rules = $request->rules();

        $invalidData = [
            'name' => '',
            'email' => 'john@example.com',
            'password' => 'password123',
        ];

        $validator = Validator::make($invalidData, $rules);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_fails_with_name_too_long()
    {
        $request = new RegisterUserRequest();
        $rules = $request->rules();

        $invalidData = [
            'name' => str_repeat('A', 256), // Too long
            'email' => 'john@example.com',
            'password' => 'password123',
        ];

        $validator = Validator::make($invalidData, $rules);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_fails_with_email_too_long()
    {
        $request = new RegisterUserRequest();
        $rules = $request->rules();

        $invalidData = [
            'name' => 'John Doe',
            'email' => str_repeat('a', 250) . '@example.com', // Too long
            'password' => 'password123',
        ];

        $validator = Validator::make($invalidData, $rules);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }
}
