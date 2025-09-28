<?php

namespace App\Events;

use App\Models\Transaction;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;

class TransactionCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $transaction;
    public $senderBalance;
    public $receiverBalance;

    public function __construct(Transaction $transaction)
    {
        $this->transaction = $transaction;
        $this->senderBalance = $transaction->sender->balance;
        $this->receiverBalance = $transaction->receiver->balance;
    }

    public function broadcastOn()
    {
        return [
            new PrivateChannel('user.' . $this->transaction->sender_id),
            new PrivateChannel('user.' . $this->transaction->receiver_id),
        ];
    }

    public function broadcastWith()
    {
        return [
            'transaction' => [
                'id' => $this->transaction->id,
                'sender_id' => $this->transaction->sender_id,
                'receiver_id' => $this->transaction->receiver_id,
                'sender_name' => $this->transaction->sender_id,
                'amount' => $this->transaction->amount,
                'commission_fee' => $this->transaction->commission_fee,
                'completed_at' => $this->transaction->completed_at,
                'type' => $this->transaction->sender_id === $this->transaction->sender->id ? 'sent' : 'received',
                'other_party' => $this->transaction->sender_id === $this->transaction->sender->id
                    ? $this->transaction->receiver->name
                    : $this->transaction->sender->name,
            ],
            'sender_balance' => $this->senderBalance,
            'receiver_balance' => $this->receiverBalance,
        ];
    }
}
