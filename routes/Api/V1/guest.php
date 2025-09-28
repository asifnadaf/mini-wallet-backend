<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Auth\RegisterUserController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;

Route::group(['middleware' => 'guest:sanctum'], function () {
    Route::post('register', [RegisterUserController::class, 'register'])->name('register');
    Route::post('login', [LoginController::class, 'login'])->name('login');
    Route::post('forgot-password/email/token', [ForgotPasswordController::class, 'emailToken']);
    Route::post('forgot-password/verify/token', [ForgotPasswordController::class, 'verifyToken']);
    Route::post('forgot-password/reset-password', [ResetPasswordController::class, 'resetPassword']);
});
