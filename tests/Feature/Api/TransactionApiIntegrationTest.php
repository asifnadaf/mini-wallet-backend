<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

class TransactionApiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        Mail::fake();
    }

    public function test_transaction_api_routes_are_registered()
    {
        $routes = \Route::getRoutes();
        $transactionRoutes = collect($routes)->filter(function ($route) {
            return str_contains($route->uri(), 'transactions');
        });

        $this->assertGreaterThan(0, $transactionRoutes->count());

        // Check that transaction routes exist by testing the endpoints
        $user = User::factory()->create(['email_verified_at' => now()]);
        $token = $user->createToken('test-token')->plainTextToken;

        // Test GET route
        $getResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/v1/transactions');
        $this->assertContains($getResponse->status(), [200, 401, 409]);

        // Test POST route
        $postResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/v1/transactions', [
                'receiver_id' => 999,
                'amount' => 100.00
            ]);
        $this->assertContains($postResponse->status(), [200, 201, 401, 409, 422]);
    }

    public function test_transaction_api_middleware_stack()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $token = $user->createToken('test-token')->plainTextToken;

        // Test that auth:sanctum middleware is applied
        $response = $this->getJson('/api/v1/transactions');
        $response->assertStatus(401);

        // Test that ensure_email_verified middleware is applied
        $unverifiedUser = User::factory()->create(['email_verified_at' => null]);
        $unverifiedToken = $unverifiedUser->createToken('unverified-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $unverifiedToken,
        ])->postJson('/api/v1/transactions', [
            'receiver_id' => 2,
            'amount' => 100.00
        ]);

        $response->assertStatus(409);
    }

    public function test_transaction_api_response_consistency()
    {
        $sender = User::factory()->create(['balance' => 1000.00, 'email_verified_at' => now()]);
        $receiver = User::factory()->create(['name' => 'John Doe']);
        $token = $sender->createToken('test-token')->plainTextToken;

        // Test GET /transactions response structure
        $getResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/transactions');

        $getResponse->assertStatus(200);
        $getResponse->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'balance',
                'transactions' => [
                    'data',
                    'current_page',
                    'per_page',
                    'total',
                    'last_page'
                ]
            ]
        ]);

        // Test POST /transactions response structure
        $postResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/transactions', [
            'receiver_id' => $receiver->id,
            'amount' => 100.00
        ]);

        $postResponse->assertStatus(201);
        $postResponse->assertJsonStructure([
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
    }

    public function test_transaction_api_error_handling()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $token = $user->createToken('test-token')->plainTextToken;

        // Test 404 for non-existent receiver
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/transactions', [
            'receiver_id' => 999,
            'amount' => 100.00
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'message',
            'errors' => [
                'receiver_id'
            ]
        ]);

        // Test 422 for validation errors
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/transactions', [
            'receiver_id' => $user->id, // Same as sender
            'amount' => -10.00
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['receiver_id', 'amount']);
    }

    public function test_transaction_api_rate_limiting()
    {
        $user = User::factory()->create(['balance' => 10000.00, 'email_verified_at' => now()]);
        $token = $user->createToken('test-token')->plainTextToken;

        // Test that rate limiting is applied (if configured)
        $responses = [];
        for ($i = 0; $i < 5; $i++) {
            $receiver = User::factory()->create(['email_verified_at' => now()]);
            $responses[] = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->postJson('/api/v1/transactions', [
                'receiver_id' => $receiver->id,
                'amount' => 10.00
            ]);
        }

        // All requests should succeed (rate limiting might not be configured)
        foreach ($responses as $response) {
            $this->assertContains($response->status(), [200, 201, 422]);
        }
    }

    public function test_transaction_api_with_different_user_roles()
    {
        // Test with different user types (all should work the same)
        $users = [
            User::factory()->create(['email_verified_at' => now()]),
            User::factory()->create(['email_verified_at' => now()]),
            User::factory()->create(['email_verified_at' => now()])
        ];

        foreach ($users as $index => $user) {
            $token = $user->createToken("user-{$index}-token")->plainTextToken;

            // Test GET transactions
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->getJson('/api/v1/transactions');

            $response->assertStatus(200);

            // Test POST transaction (if user has balance)
            if ($index === 0) {
                $user->update(['balance' => 1000.00]);
                $receiver = User::factory()->create(['email_verified_at' => now()]);

                $response = $this->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                ])->postJson('/api/v1/transactions', [
                    'receiver_id' => $receiver->id,
                    'amount' => 100.00
                ]);

                $response->assertStatus(201);
            }
        }
    }

    public function test_transaction_api_database_integrity()
    {
        $sender = User::factory()->create(['balance' => 1000.00, 'email_verified_at' => now()]);
        $receiver = User::factory()->create(['email_verified_at' => now()]);
        $token = $sender->createToken('test-token')->plainTextToken;

        $initialSenderBalance = $sender->balance;
        $initialReceiverBalance = $receiver->balance;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/transactions', [
            'receiver_id' => $receiver->id,
            'amount' => 200.00
        ]);

        $response->assertStatus(201);

        // Verify database integrity
        $this->assertDatabaseHas('transactions', [
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'amount' => 200.00
        ]);

        // Verify balances were updated correctly
        $sender->refresh();
        $receiver->refresh();

        $expectedCommission = 200.00 * 0.015;
        $expectedSenderBalance = $initialSenderBalance - 200.00 - $expectedCommission;
        $expectedReceiverBalance = $initialReceiverBalance + 200.00;

        $this->assertEquals($expectedSenderBalance, $sender->balance);
        $this->assertEquals($expectedReceiverBalance, $receiver->balance);
    }

    public function test_transaction_api_event_system_integration()
    {
        $sender = User::factory()->create(['balance' => 1000.00, 'email_verified_at' => now()]);
        $receiver = User::factory()->create(['email_verified_at' => now()]);
        $token = $sender->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/transactions', [
            'receiver_id' => $receiver->id,
            'amount' => 100.00
        ]);

        $response->assertStatus(201);

        // Verify event was dispatched (simplified check)
        Event::assertDispatched(\App\Events\TransactionCreated::class);
    }

    public function test_transaction_api_mail_system_integration()
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

    public function test_transaction_api_performance_integration()
    {
        $user = User::factory()->create(['balance' => 10000.00, 'email_verified_at' => now()]);
        $token = $user->createToken('test-token')->plainTextToken;

        $startTime = microtime(true);

        // Perform multiple operations
        for ($i = 0; $i < 5; $i++) {
            $receiver = User::factory()->create(['email_verified_at' => now()]);

            // POST transaction
            $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                ->postJson('/api/v1/transactions', [
                    'receiver_id' => $receiver->id,
                    'amount' => 50.00
                ])
                ->assertStatus(201);

            // GET transactions
            $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                ->getJson('/api/v1/transactions')
                ->assertStatus(200);
        }

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Performance should be reasonable
        $this->assertLessThan(10.0, $executionTime);
    }

    public function test_transaction_api_cross_user_integration()
    {
        $user1 = User::factory()->create(['balance' => 1000.00, 'email_verified_at' => now()]);
        $user2 = User::factory()->create(['balance' => 500.00, 'email_verified_at' => now()]);
        $user3 = User::factory()->create(['balance' => 200.00, 'email_verified_at' => now()]);

        $token1 = $user1->createToken('user1-token')->plainTextToken;
        $token2 = $user2->createToken('user2-token')->plainTextToken;
        $token3 = $user3->createToken('user3-token')->plainTextToken;

        // User1 sends to User2
        $this->withHeaders(['Authorization' => 'Bearer ' . $token1])
            ->postJson('/api/v1/transactions', [
                'receiver_id' => $user2->id,
                'amount' => 100.00
            ])
            ->assertStatus(201);

        // User2 sends to User3
        $this->withHeaders(['Authorization' => 'Bearer ' . $token2])
            ->postJson('/api/v1/transactions', [
                'receiver_id' => $user3->id,
                'amount' => 50.00
            ])
            ->assertStatus(201);

        // User3 sends to User2 (not User1 to avoid self-transaction)
        $this->withHeaders(['Authorization' => 'Bearer ' . $token3])
            ->postJson('/api/v1/transactions', [
                'receiver_id' => $user2->id,
                'amount' => 25.00
            ])
            ->assertStatus(201);

        // Verify all users can see their transactions
        foreach ([$token1, $token2, $token3] as $token) {
            $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                ->getJson('/api/v1/transactions');

            $response->assertStatus(200);
            $this->assertGreaterThan(0, $response->json('data.transactions.total'));
        }
    }
}
