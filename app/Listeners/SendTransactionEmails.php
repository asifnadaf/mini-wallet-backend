<?php

namespace App\Listeners;

use App\Events\TransactionCreated;
use App\Mail\TransactionDebitMail;
use App\Mail\TransactionCreditMail;
use Illuminate\Support\Facades\Mail;

class SendTransactionEmails
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(TransactionCreated $event): void
    {
        $transaction = $event->transaction;

        // Send debit email to sender
        Mail::to($transaction->sender->email)
            ->queue(new TransactionDebitMail($transaction));

        // Send credit email to receiver
        Mail::to($transaction->receiver->email)
            ->queue(new TransactionCreditMail($transaction));
    }
}
