<?php

namespace App\Http\Controllers\Api\V1\Transactions;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\TransactionRequest;
use App\Services\TransactionService;
use App\Events\TransactionCreated;
use App\Mail\TransactionCreditMail;
use App\Mail\TransactionDebitMail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use App\Contracts\ApiResponseInterface;

class TransactionController extends BaseApiController
{
    protected TransactionService $transactionService;

    public function __construct(
        TransactionService $transactionService,
        ApiResponseInterface $apiResponse
    ) {
        parent::__construct($apiResponse);

        $this->transactionService = $transactionService;
    }

    /**
     * List authenticated user's transactions
     */
    public function index()
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->unauthorized('Unauthenticated');
            }

            $perPage = request()->get('per_page', null);

            // Get paginated transactions
            $transactions = $this->transactionService->getUserTransactions($user, $perPage);

            // Return paginated response
            return $this->paginated([
                'balance' => $user->balance,
                'transactions' => $transactions
            ], 'Transactions retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve transactions', $e->getMessage());
        }
    }

    /**
     * Transfer money to another user
     */
    public function store(TransactionRequest $request)
    {
        try {
            $receiver = User::findOrFail($request->receiver_id);
            /** @var \App\Models\User $sender */
            $sender = Auth::user();

            $transaction = $this->transactionService->transferMoney(
                $sender,
                $receiver,
                $request->amount
            );

            // Refresh sender to get updated balance
            $sender->refresh();

            // Broadcast the event (this also triggers local event listeners)
            broadcast(new TransactionCreated($transaction));

            return $this->created([
                'transaction' => [
                    'id' => $transaction->id,
                    'amount' => $transaction->amount,
                    'commission_fee' => $transaction->commission_fee,
                    'receiver_name' => $receiver->name,
                    'completed_at' => $transaction->completed_at,
                ],
                'new_balance' => $sender->balance
            ], 'Transfer successful');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), null, 422);
        }
    }
}
