<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\ChangePasswordRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ChangePasswordRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_change_password_request_can_be_instantiated()
    {
        $request = new ChangePasswordRequest();
        $this->assertInstanceOf(ChangePasswordRequest::class, $request);
    }

    public function test_authorize_returns_true()
    {
        $request = new ChangePasswordRequest();
        $this->assertTrue($request->authorize());
    }

    public function test_rules_returns_correct_validation_rules()
    {
        $request = new ChangePasswordRequest();
        $rules = $request->rules();

        $this->assertArrayHasKey('old_password', $rules);
        $this->assertArrayHasKey('new_password', $rules);

        $this->assertContains('required', $rules['old_password']);
        $this->assertContains('required', $rules['new_password']);
        $this->assertContains('different:old_password', $rules['new_password']);
    }

    public function test_validation_passes_with_valid_data()
    {
        $request = new ChangePasswordRequest();
        $rules = $request->rules();

        $data = [
            'old_password' => 'oldpassword123',
            'new_password' => 'newpassword123',
        ];

        $validator = Validator::make($data, $rules);
        $this->assertTrue($validator->passes());
    }

    public function test_validation_fails_without_old_password()
    {
        $request = new ChangePasswordRequest();
        $rules = $request->rules();

        $data = [
            'new_password' => 'newpassword123',
        ];

        $validator = Validator::make($data, $rules);
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('old_password', $validator->errors()->toArray());
    }

    public function test_validation_fails_without_new_password()
    {
        $request = new ChangePasswordRequest();
        $rules = $request->rules();

        $data = [
            'old_password' => 'oldpassword123',
        ];

        $validator = Validator::make($data, $rules);
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('new_password', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_old_and_new_passwords_are_same()
    {
        $request = new ChangePasswordRequest();
        $rules = $request->rules();

        $data = [
            'old_password' => 'samepassword123',
            'new_password' => 'samepassword123',
        ];

        $validator = Validator::make($data, $rules);
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('new_password', $validator->errors()->toArray());
    }

    public function test_validation_fails_with_empty_old_password()
    {
        $request = new ChangePasswordRequest();
        $rules = $request->rules();

        $data = [
            'old_password' => '',
            'new_password' => 'newpassword123',
        ];

        $validator = Validator::make($data, $rules);
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('old_password', $validator->errors()->toArray());
    }

    public function test_validation_fails_with_empty_new_password()
    {
        $request = new ChangePasswordRequest();
        $rules = $request->rules();

        $data = [
            'old_password' => 'oldpassword123',
            'new_password' => '',
        ];

        $validator = Validator::make($data, $rules);
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('new_password', $validator->errors()->toArray());
    }

    public function test_validation_fails_with_null_old_password()
    {
        $request = new ChangePasswordRequest();
        $rules = $request->rules();

        $data = [
            'old_password' => null,
            'new_password' => 'newpassword123',
        ];

        $validator = Validator::make($data, $rules);
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('old_password', $validator->errors()->toArray());
    }

    public function test_validation_fails_with_null_new_password()
    {
        $request = new ChangePasswordRequest();
        $rules = $request->rules();

        $data = [
            'old_password' => 'oldpassword123',
            'new_password' => null,
        ];

        $validator = Validator::make($data, $rules);
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('new_password', $validator->errors()->toArray());
    }

    public function test_validation_passes_with_different_passwords()
    {
        $request = new ChangePasswordRequest();
        $rules = $request->rules();

        $data = [
            'old_password' => 'oldpassword123',
            'new_password' => 'completelydifferentpassword456',
        ];

        $validator = Validator::make($data, $rules);
        $this->assertTrue($validator->passes());
    }

    public function test_validation_passes_with_whitespace_differences()
    {
        $request = new ChangePasswordRequest();
        $rules = $request->rules();

        $data = [
            'old_password' => 'oldpassword123',
            'new_password' => ' oldpassword123 ', // Same but with spaces
        ];

        $validator = Validator::make($data, $rules);
        $this->assertTrue($validator->passes());
    }

    public function test_validation_passes_with_case_differences()
    {
        $request = new ChangePasswordRequest();
        $rules = $request->rules();

        $data = [
            'old_password' => 'oldpassword123',
            'new_password' => 'OLDPASSWORD123', // Same but different case
        ];

        $validator = Validator::make($data, $rules);
        $this->assertTrue($validator->passes());
    }
}
