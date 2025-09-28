<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    protected float $commissionRate;
    protected int $defaultPerPage;

    public function __construct()
    {
        $this->commissionRate = config('transactions.commission_rate', 0.015);
        $this->defaultPerPage = config('transactions.default_per_page', 10);
    }

    public function transferMoney(User $sender, User $receiver, float $amount): Transaction
    {
        return DB::transaction(function () use ($sender, $receiver, $amount) {
            // Calculate commission
            $commissionFee = $amount * $this->commissionRate;
            $totalDebit = $amount + $commissionFee;

            // Lock sender's row for update to prevent race conditions
            $lockedSender = User::where('id', $sender->id)->lockForUpdate()->first();

            if ($lockedSender->balance < $totalDebit) {
                throw new \Exception('Insufficient balance');
            }

            // Update balances
            $lockedSender->decrement('balance', $totalDebit);
            $receiver->increment('balance', $amount);

            // Create transaction record
            $transaction = Transaction::create([
                'sender_id' => $sender->id,
                'receiver_id' => $receiver->id,
                'amount' => $amount,
                'commission_fee' => $commissionFee,
                'completed_at' => now(),
            ]);

            return $transaction;
        });
    }

    public function getUserTransactions(User $user, ?int $perPage = null)
    {
        $perPage = $perPage ?? $this->defaultPerPage;

        $query = Transaction::with(['sender', 'receiver'])
            ->where(function ($q) use ($user) {
                $q->where('sender_id', $user->id)
                    ->orWhere('receiver_id', $user->id);
            })
            ->orderBy('completed_at', 'desc');

        $paginator = $query->paginate($perPage);

        // Transform the items
        $paginator->getCollection()->transform(function ($transaction) use ($user) {
            if ($transaction->sender_id == $user->id) {
                $transaction->type = 'sent';
                $transaction->other_party = $transaction->receiver->name;
            } else {
                $transaction->type = 'received';
                $transaction->other_party = $transaction->sender->name;
            }
            return $transaction;
        });

        return $paginator;
    }
}
