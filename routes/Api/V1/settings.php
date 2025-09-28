<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Settings\UserController;
use App\Http\Controllers\Api\V1\Settings\ChangePasswordController;
use App\Http\Controllers\Api\V1\Auth\LoginController;

Route::group(['middleware' => 'auth:sanctum'], function () {

    Route::get('user', [UserController::class, 'show'])->name('user.show');
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');

    Route::group(['middleware' => ['ensure_email_verified']], function () {
        Route::post('change-password', [ChangePasswordController::class, 'store'])->name('change-password.store');
    });
});
