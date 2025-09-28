<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Auth\EmailTokenController;

Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::post('email/send-token', [EmailTokenController::class, 'sendToken']);
    Route::post('email/verify-token', [EmailTokenController::class, 'verifyToken']);
});
