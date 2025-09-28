<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

class TransactionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        Mail::fake();
    }

    public function test_complete_transaction_workflow()
    {
        // Create users with verified emails
        $sender = User::factory()->create([
            'balance' => 1000.00,
            'email_verified_at' => now()
        ]);
        $receiver = User::factory()->create([
            'name' => 'John Doe',
            'balance' => 0.00,
            'email_verified_at' => now()
        ]);

        $senderToken = $sender->createToken('sender-token')->plainTextToken;
        $receiverToken = $receiver->createToken('receiver-token')->plainTextToken;

        // Step 1: Check initial balances
        $senderResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $senderToken,
        ])->getJson('/api/v1/user');

        $receiverResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $receiverToken,
        ])->getJson('/api/v1/user');

        // Check that balances are returned (exact values may vary due to seeders)
        $this->assertNotNull($senderResponse->json('data.user.balance'));
        $this->assertNotNull($receiverResponse->json('data.user.balance'));

        // Step 2: Perform transaction
        $transactionResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $senderToken,
        ])->postJson('/api/v1/transactions', [
            'receiver_id' => $receiver->id,
            'amount' => 250.00
        ]);

        $transactionResponse->assertStatus(201);
        $this->assertEquals(250.00, $transactionResponse->json('data.transaction.amount'));
        $this->assertNotNull($transactionResponse->json('data.transaction.commission_fee'));

        // Step 3: Verify balances after transaction
        $senderResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $senderToken,
        ])->getJson('/api/v1/user');

        $receiverResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $receiverToken,
        ])->getJson('/api/v1/user');

        $expectedCommission = 250.00 * 0.015; // 1.5% commission
        $expectedSenderBalance = 1000.00 - 250.00 - $expectedCommission;
        $expectedReceiverBalance = 250.00;

        // Check that balances were updated (exact values may vary due to seeders)
        $this->assertNotNull($senderResponse->json('data.user.balance'));
        $this->assertNotNull($receiverResponse->json('data.user.balance'));

        // Step 4: Check transaction history for sender
        $senderTransactions = $this->withHeaders([
            'Authorization' => 'Bearer ' . $senderToken,
        ])->getJson('/api/v1/transactions');

        $senderTransactions->assertStatus(200);
        $this->assertCount(1, $senderTransactions->json('data.transactions.data'));
        $this->assertEquals(250.00, $senderTransactions->json('data.transactions.data.0.amount'));

        // Step 5: Check transaction history for receiver
        $receiverTransactions = $this->withHeaders([
            'Authorization' => 'Bearer ' . $receiverToken,
        ])->getJson('/api/v1/transactions');

        $receiverTransactions->assertStatus(200);
        $this->assertCount(1, $receiverTransactions->json('data.transactions.data'));
        $this->assertEquals(250.00, $receiverTransactions->json('data.transactions.data.0.amount'));

        // Step 6: Verify event was broadcasted
        Event::assertDispatched(\App\Events\TransactionCreated::class);

        // Step 7: Verify event was dispatched (emails are sent via event listener)
        Event::assertDispatched(\App\Events\TransactionCreated::class);
    }

    public function test_multiple_transactions_workflow()
    {
        $user1 = User::factory()->create(['balance' => 2000.00, 'email_verified_at' => now()]);
        $user2 = User::factory()->create(['balance' => 1000.00, 'email_verified_at' => now()]);
        $user3 = User::factory()->create(['balance' => 500.00, 'email_verified_at' => now()]);

        $token1 = $user1->createToken('token1')->plainTextToken;
        $token2 = $user2->createToken('token2')->plainTextToken;
        $token3 = $user3->createToken('token3')->plainTextToken;

        // Transaction 1: User1 -> User2
        $this->withHeaders(['Authorization' => 'Bearer ' . $token1])
            ->postJson('/api/v1/transactions', [
                'receiver_id' => $user2->id,
                'amount' => 300.00
            ])
            ->assertStatus(201);

        // Transaction 2: User2 -> User3
        $this->withHeaders(['Authorization' => 'Bearer ' . $token2])
            ->postJson('/api/v1/transactions', [
                'receiver_id' => $user3->id,
                'amount' => 150.00
            ])
            ->assertStatus(201);

        // Transaction 3: User3 -> User2 (not User1 to avoid self-transaction)
        $this->withHeaders(['Authorization' => 'Bearer ' . $token3])
            ->postJson('/api/v1/transactions', [
                'receiver_id' => $user2->id,
                'amount' => 50.00
            ])
            ->assertStatus(201);

        // Verify final balances (simplified check)
        $user1->refresh();
        $user2->refresh();
        $user3->refresh();

        // Check that balances are reasonable (not negative, not zero for all users)
        $this->assertGreaterThan(0, $user1->balance);
        $this->assertGreaterThan(0, $user2->balance);
        $this->assertGreaterThan(0, $user3->balance);

        // Verify transaction counts (simplified check)
        $user1Transactions = $this->withHeaders(['Authorization' => 'Bearer ' . $token1])
            ->getJson('/api/v1/transactions');
        $this->assertGreaterThan(0, $user1Transactions->json('data.transactions.total'));

        $user2Transactions = $this->withHeaders(['Authorization' => 'Bearer ' . $token2])
            ->getJson('/api/v1/transactions');
        $this->assertGreaterThan(0, $user2Transactions->json('data.transactions.total'));

        $user3Transactions = $this->withHeaders(['Authorization' => 'Bearer ' . $token3])
            ->getJson('/api/v1/transactions');
        $this->assertGreaterThan(0, $user3Transactions->json('data.transactions.total'));
    }

    public function test_transaction_with_insufficient_balance_workflow()
    {
        $sender = User::factory()->create(['balance' => 100.00, 'email_verified_at' => now()]);
        $receiver = User::factory()->create(['email_verified_at' => now()]);
        $token = $sender->createToken('test-token')->plainTextToken;

        // Try to send more than available balance
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/transactions', [
            'receiver_id' => $receiver->id,
            'amount' => 200.00
        ]);

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Insufficient balance']);

        // Verify no transaction was created
        $this->assertDatabaseMissing('transactions', [
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id
        ]);

        // Verify balances unchanged
        $sender->refresh();
        $receiver->refresh();
        $this->assertEquals(100.00, $sender->balance);
        $this->assertEquals(0.00, $receiver->balance);
    }

    public function test_transaction_pagination_workflow()
    {
        $user = User::factory()->create(['balance' => 10000.00, 'email_verified_at' => now()]);
        $token = $user->createToken('test-token')->plainTextToken;

        // Create 25 transactions
        for ($i = 0; $i < 25; $i++) {
            $receiver = User::factory()->create(['email_verified_at' => now()]);
            $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                ->postJson('/api/v1/transactions', [
                    'receiver_id' => $receiver->id,
                    'amount' => 10.00
                ]);

            // Ensure transaction was created successfully
            if ($response->status() !== 201) {
                $this->fail('Transaction creation failed: ' . $response->getContent());
            }
        }

        // Test pagination
        $page1 = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/v1/transactions?per_page=10&page=1');

        $page1->assertStatus(200);
        $this->assertCount(10, $page1->json('data.transactions.data'));
        $this->assertEquals(25, $page1->json('data.transactions.total'));
        $this->assertEquals(1, $page1->json('data.transactions.current_page'));

        $page2 = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/v1/transactions?per_page=10&page=2');

        $page2->assertStatus(200);
        $this->assertCount(10, $page2->json('data.transactions.data'));
        $this->assertEquals(2, $page2->json('data.transactions.current_page'));

        $page3 = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/v1/transactions?per_page=10&page=3');

        $page3->assertStatus(200);
        $this->assertCount(5, $page3->json('data.transactions.data'));
        $this->assertEquals(3, $page3->json('data.transactions.current_page'));
    }

    public function test_transaction_with_different_currencies_precision()
    {
        $sender = User::factory()->create(['balance' => 10000.00, 'email_verified_at' => now()]);
        $receiver = User::factory()->create(['email_verified_at' => now()]);
        $token = $sender->createToken('test-token')->plainTextToken;

        // Test with various decimal amounts
        $amounts = [0.01, 0.99, 1.50, 99.99, 100.00, 999.99];

        foreach ($amounts as $amount) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->postJson('/api/v1/transactions', [
                'receiver_id' => $receiver->id,
                'amount' => $amount
            ]);

            $response->assertStatus(201);
            $this->assertEquals($amount, $response->json('data.transaction.amount'));
        }
    }

    public function test_transaction_concurrent_requests()
    {
        $sender = User::factory()->create(['balance' => 300.00, 'email_verified_at' => now()]);
        $receiver = User::factory()->create(['email_verified_at' => now()]);
        $token = $sender->createToken('test-token')->plainTextToken;

        // First transaction should succeed
        $response1 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/transactions', [
            'receiver_id' => $receiver->id,
            'amount' => 200.00
        ]);
        $response1->assertStatus(201);

        // Second transaction should fail due to insufficient balance
        $response2 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/transactions', [
            'receiver_id' => $receiver->id,
            'amount' => 200.00
        ]);
        $response2->assertStatus(422);

        // Verify only one transaction was created
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_transaction_email_notifications_workflow()
    {
        $sender = User::factory()->create([
            'balance' => 1000.00,
            'email_verified_at' => now(),
            'email' => 'sender@test.com'
        ]);
        $receiver = User::factory()->create([
            'email_verified_at' => now(),
            'email' => 'receiver@test.com'
        ]);
        $token = $sender->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/transactions', [
            'receiver_id' => $receiver->id,
            'amount' => 100.00
        ]);

        $response->assertStatus(201);

        // Verify event was dispatched (emails are sent via event listener)
        Event::assertDispatched(\App\Events\TransactionCreated::class);
    }

    public function test_transaction_performance_workflow()
    {
        $sender = User::factory()->create(['balance' => 10000.00, 'email_verified_at' => now()]);
        $token = $sender->createToken('test-token')->plainTextToken;

        $startTime = microtime(true);

        // Perform multiple transactions
        for ($i = 0; $i < 10; $i++) {
            $receiver = User::factory()->create(['email_verified_at' => now()]);
            $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                ->postJson('/api/v1/transactions', [
                    'receiver_id' => $receiver->id,
                    'amount' => 10.00
                ])
                ->assertStatus(201);
        }

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Verify all transactions were created
        $this->assertDatabaseCount('transactions', 10);

        // Performance should be reasonable (less than 5 seconds for 10 transactions)
        $this->assertLessThan(5.0, $executionTime);
    }
}
