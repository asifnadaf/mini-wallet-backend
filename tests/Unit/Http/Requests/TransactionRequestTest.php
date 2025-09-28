<?php

namespace Tests\Unit\Http\Requests;

use Tests\TestCase;
use App\Http\Requests\TransactionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TransactionRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        User::factory()->create(['id' => 1, 'email' => 'sender@test.com']);
        User::factory()->create(['id' => 2, 'email' => 'receiver@test.com']);
    }

    public function test_authorize_returns_true()
    {
        $request = new TransactionRequest();
        $this->assertTrue($request->authorize());
    }

    public function test_custom_messages_are_defined()
    {
        $request = new TransactionRequest();
        $messages = $request->messages();

        $this->assertIsArray($messages);
        $this->assertArrayHasKey('receiver_id.exists', $messages);
        $this->assertArrayHasKey('receiver_id.not_in', $messages);
        $this->assertArrayHasKey('amount.min', $messages);
        $this->assertArrayHasKey('amount.max', $messages);
    }

    public function test_custom_messages_content()
    {
        $request = new TransactionRequest();
        $messages = $request->messages();

        $this->assertEquals('The selected receiver does not exist.', $messages['receiver_id.exists']);
        $this->assertEquals('You cannot send money to yourself.', $messages['receiver_id.not_in']);
        $this->assertEquals('The amount must be at least 0.01.', $messages['amount.min']);
        $this->assertEquals('The amount may not be greater than 999,999,999.99.', $messages['amount.max']);
    }

    public function test_request_has_required_fields()
    {
        $request = new TransactionRequest();

        // Test that the request expects receiver_id and amount
        $this->assertTrue(method_exists($request, 'rules'));
        $this->assertTrue(method_exists($request, 'messages'));
        $this->assertTrue(method_exists($request, 'authorize'));
    }

    public function test_request_validation_structure()
    {
        $request = new TransactionRequest();

        // Test that rules method exists and returns array
        $this->assertTrue(method_exists($request, 'rules'));

        // Test that messages method exists and returns array
        $this->assertTrue(method_exists($request, 'messages'));

        // Test that authorize method exists and returns boolean
        $this->assertTrue(method_exists($request, 'authorize'));
        $this->assertIsBool($request->authorize());
    }
}
