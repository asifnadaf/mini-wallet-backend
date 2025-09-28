<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Transactions\TransactionController;

Route::group(['middleware' => ['auth:sanctum', 'ensure_email_verified']], function () {
    Route::get('transactions', [TransactionController::class, 'index']);
    Route::post('transactions', [TransactionController::class, 'store']);
});
