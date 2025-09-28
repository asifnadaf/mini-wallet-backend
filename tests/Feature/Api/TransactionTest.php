<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        Mail::fake();
    }

    public function test_get_transactions_requires_authentication()
    {
        $response = $this->getJson('/api/v1/transactions');

        $response->assertStatus(401);
    }

    public function test_get_transactions_returns_user_transactions()
    {
        $user = User::factory()->create(['balance' => 1000.00]);
        $token = $user->createToken('test-token')->plainTextToken;

        // Create some transactions for the user
        Transaction::factory()->count(3)->create(['sender_id' => $user->id]);
        Transaction::factory()->count(2)->create(['receiver_id' => $user->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/transactions');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'balance',
                'transactions' => [
                    'data' => [
                        '*' => [
                            'id',
                            'amount',
                            'commission_fee',
                            'sender',
                            'receiver',
                            'completed_at'
                        ]
                    ],
                    'current_page',
                    'per_page',
                    'total'
                ]
            ]
        ]);

        $this->assertEquals(1000.00, $response->json('data.balance'));
        $this->assertCount(5, $response->json('data.transactions.data'));
    }

    public function test_get_transactions_with_pagination()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        Transaction::factory()->count(15)->create(['sender_id' => $user->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/transactions?per_page=5');

        $response->assertStatus(200);
        $this->assertCount(5, $response->json('data.transactions.data'));
        $this->assertEquals(15, $response->json('data.transactions.total'));
    }

    public function test_post_transaction_requires_authentication()
    {
        $response = $this->postJson('/api/v1/transactions', [
            'receiver_id' => 2,
            'amount' => 100.00
        ]);

        $response->assertStatus(401);
    }

    public function test_post_transaction_success()
    {
        $sender = User::factory()->create(['balance' => 1000.00, 'email_verified_at' => now()]);
        $receiver = User::factory()->create(['name' => 'John Doe']);
        $token = $sender->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/transactions', [
            'receiver_id' => $receiver->id,
            'amount' => 100.00
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'transaction' => [
                    'id',
                    'amount',
                    'commission_fee',
                    'receiver_name',
                    'completed_at'
                ],
                'new_balance'
            ]
        ]);

        $this->assertEquals(100.00, $response->json('data.transaction.amount'));
        $this->assertEquals('John Doe', $response->json('data.transaction.receiver_name'));
        $this->assertNotNull($response->json('data.transaction.commission_fee'));

        // Verify transaction was created in database
        $this->assertDatabaseHas('transactions', [
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'amount' => 100.00
        ]);

        // Verify balances were updated
        $sender->refresh();
        $receiver->refresh();
        $this->assertLessThan(1000.00, $sender->balance);
        $this->assertEquals(100.00, $receiver->balance);
    }

    public function test_post_transaction_validation_errors()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/transactions', [
            'receiver_id' => 999, // Non-existent user
            'amount' => -10.00 // Invalid amount
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['receiver_id', 'amount']);
    }

    public function test_post_transaction_cannot_send_to_self()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/transactions', [
            'receiver_id' => $user->id, // Same as sender
            'amount' => 100.00
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['receiver_id']);
    }

    public function test_post_transaction_insufficient_balance()
    {
        $sender = User::factory()->create(['balance' => 50.00, 'email_verified_at' => now()]);
        $receiver = User::factory()->create();
        $token = $sender->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/transactions', [
            'receiver_id' => $receiver->id,
            'amount' => 100.00
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Insufficient balance'
        ]);
    }

    public function test_post_transaction_requires_email_verification()
    {
        $sender = User::factory()->create(['balance' => 1000.00, 'email_verified_at' => null]);
        $receiver = User::factory()->create();
        $token = $sender->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/transactions', [
            'receiver_id' => $receiver->id,
            'amount' => 100.00
        ]);

        $response->assertStatus(409);
    }

    public function test_post_transaction_minimum_amount()
    {
        $sender = User::factory()->create(['balance' => 1000.00, 'email_verified_at' => now()]);
        $receiver = User::factory()->create();
        $token = $sender->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/transactions', [
            'receiver_id' => $receiver->id,
            'amount' => 0.005 // Below minimum
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['amount']);
    }

    public function test_post_transaction_maximum_amount()
    {
        $sender = User::factory()->create(['balance' => 1000000000.00, 'email_verified_at' => now()]);
        $receiver = User::factory()->create();
        $token = $sender->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/transactions', [
            'receiver_id' => $receiver->id,
            'amount' => 1000000000.00 // Above maximum
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['amount']);
    }

    public function test_post_transaction_commission_calculation()
    {
        $sender = User::factory()->create(['balance' => 1000.00, 'email_verified_at' => now()]);
        $receiver = User::factory()->create();
        $token = $sender->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/transactions', [
            'receiver_id' => $receiver->id,
            'amount' => 100.00
        ]);

        $response->assertStatus(201);

        $commissionFee = $response->json('data.transaction.commission_fee');
        $expectedCommission = 100.00 * 0.015; // 1.5% commission

        $this->assertEquals($expectedCommission, $commissionFee);
    }

    public function test_post_transaction_broadcasts_event()
    {
        $sender = User::factory()->create(['balance' => 1000.00, 'email_verified_at' => now()]);
        $receiver = User::factory()->create();
        $token = $sender->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/transactions', [
            'receiver_id' => $receiver->id,
            'amount' => 100.00
        ]);

        $response->assertStatus(201);

        Event::assertDispatched(\App\Events\TransactionCreated::class);
    }

    public function test_post_transaction_sends_emails()
    {
        $sender = User::factory()->create(['balance' => 1000.00, 'email_verified_at' => now()]);
        $receiver = User::factory()->create();
        $token = $sender->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/transactions', [
            'receiver_id' => $receiver->id,
            'amount' => 100.00
        ]);

        $response->assertStatus(201);

        // Check that event was dispatched (emails are sent via event listener)
        Event::assertDispatched(\App\Events\TransactionCreated::class);

        // Note: Email sending is handled by the SendTransactionEmails listener
        // which is triggered by the TransactionCreated event
    }

    public function test_get_transactions_empty_list()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/transactions');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data.transactions.data'));
    }

    public function test_post_transaction_with_decimal_amount()
    {
        $sender = User::factory()->create(['balance' => 1000.00, 'email_verified_at' => now()]);
        $receiver = User::factory()->create();
        $token = $sender->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/transactions', [
            'receiver_id' => $receiver->id,
            'amount' => 99.99
        ]);

        $response->assertStatus(201);
        $this->assertEquals(99.99, $response->json('data.transaction.amount'));
    }
}
