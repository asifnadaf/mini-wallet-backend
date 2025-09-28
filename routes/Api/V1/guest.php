<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Auth\RegisterUserController;

Route::group(['middleware' => 'guest:sanctum'], function () {
    Route::post('register', [RegisterUserController::class, 'register'])->name('register');
});
