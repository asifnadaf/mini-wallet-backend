<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Auth\LoginController;

Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::post('logout', [LoginController::class, 'logout'])->name('logout')->middleware('throttle:10,1');
});
