<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\TransactionService;
use App\Models\User;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class TransactionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TransactionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TransactionService();
    }

    public function test_service_can_be_instantiated()
    {
        $this->assertInstanceOf(TransactionService::class, $this->service);
    }

    public function test_service_has_required_methods()
    {
        $this->assertTrue(method_exists($this->service, 'transferMoney'));
        $this->assertTrue(method_exists($this->service, 'getUserTransactions'));
    }

    public function test_transfer_money_success()
    {
        $sender = User::factory()->create(['balance' => 1000.00]);
        $receiver = User::factory()->create(['balance' => 500.00]);
        $amount = 100.00;

        $transaction = $this->service->transferMoney($sender, $receiver, $amount);

        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertEquals($sender->id, $transaction->sender_id);
        $this->assertEquals($receiver->id, $transaction->receiver_id);
        $this->assertEquals($amount, $transaction->amount);
        $this->assertNotNull($transaction->commission_fee);
        $this->assertNotNull($transaction->completed_at);

        // Check balances were updated
        $sender->refresh();
        $receiver->refresh();

        $expectedCommission = $amount * 0.015; // 1.5% commission
        $expectedSenderBalance = 1000.00 - $amount - $expectedCommission;
        $expectedReceiverBalance = 500.00 + $amount;

        $this->assertEquals($expectedSenderBalance, $sender->balance);
        $this->assertEquals($expectedReceiverBalance, $receiver->balance);
    }

    public function test_transfer_money_insufficient_balance()
    {
        $sender = User::factory()->create(['balance' => 50.00]);
        $receiver = User::factory()->create(['balance' => 100.00]);
        $amount = 100.00;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient balance');

        $this->service->transferMoney($sender, $receiver, $amount);
    }

    public function test_transfer_money_calculates_commission_correctly()
    {
        $sender = User::factory()->create(['balance' => 1000.00]);
        $receiver = User::factory()->create(['balance' => 0.00]);
        $amount = 200.00;

        $transaction = $this->service->transferMoney($sender, $receiver, $amount);

        $expectedCommission = 200.00 * 0.015; // 1.5% commission
        $this->assertEquals($expectedCommission, $transaction->commission_fee);
    }

    public function test_transfer_money_uses_database_transaction()
    {
        $sender = User::factory()->create(['balance' => 1000.00]);
        $receiver = User::factory()->create(['balance' => 0.00]);
        $amount = 100.00;

        // Mock DB::transaction to verify it's called
        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->service->transferMoney($sender, $receiver, $amount);
    }

    public function test_get_user_transactions_returns_paginated_results()
    {
        $user = User::factory()->create();

        // Create some transactions
        Transaction::factory()->count(5)->create(['sender_id' => $user->id]);
        Transaction::factory()->count(3)->create(['receiver_id' => $user->id]);

        $result = $this->service->getUserTransactions($user, 5);

        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $result);
        $this->assertEquals(5, $result->perPage());
        $this->assertEquals(8, $result->total()); // 5 sent + 3 received
    }

    public function test_get_user_transactions_with_default_per_page()
    {
        $user = User::factory()->create();

        Transaction::factory()->count(15)->create(['sender_id' => $user->id]);

        $result = $this->service->getUserTransactions($user);

        $this->assertEquals(10, $result->perPage()); // Default per page
    }

    public function test_get_user_transactions_includes_relationships()
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        Transaction::factory()->create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id
        ]);

        $result = $this->service->getUserTransactions($sender);

        $transaction = $result->items()[0];
        $this->assertTrue($transaction->relationLoaded('sender'));
        $this->assertTrue($transaction->relationLoaded('receiver'));
    }

    public function test_commission_rate_configuration()
    {
        // Test that commission rate can be configured
        config(['transactions.commission_rate' => 0.02]); // 2%

        $service = new TransactionService();
        $sender = User::factory()->create(['balance' => 1000.00]);
        $receiver = User::factory()->create(['balance' => 0.00]);
        $amount = 100.00;

        $transaction = $service->transferMoney($sender, $receiver, $amount);

        $expectedCommission = 100.00 * 0.02; // 2% commission
        $this->assertEquals($expectedCommission, $transaction->commission_fee);
    }

    public function test_transfer_money_with_large_amount()
    {
        $sender = User::factory()->create(['balance' => 1000000.00]);
        $receiver = User::factory()->create(['balance' => 0.00]);
        $amount = 500000.00;

        $transaction = $this->service->transferMoney($sender, $receiver, $amount);

        $this->assertEquals($amount, $transaction->amount);
        $this->assertNotNull($transaction->commission_fee);

        $sender->refresh();
        $receiver->refresh();

        $this->assertGreaterThan(0, $sender->balance);
        $this->assertEquals($amount, $receiver->balance);
    }

    public function test_transfer_money_with_small_amount()
    {
        $sender = User::factory()->create(['balance' => 10.00]);
        $receiver = User::factory()->create(['balance' => 0.00]);
        $amount = 0.01; // Minimum amount

        $transaction = $this->service->transferMoney($sender, $receiver, $amount);

        $this->assertEquals($amount, $transaction->amount);
        $this->assertNotNull($transaction->commission_fee);

        $sender->refresh();
        $receiver->refresh();

        $this->assertGreaterThan(0, $sender->balance);
        $this->assertEquals($amount, $receiver->balance);
    }
}
