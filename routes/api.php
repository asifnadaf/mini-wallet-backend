<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\ServerStatusController;

Route::group(['prefix' => 'v1', 'as' => 'api.'], function () {
    Route::get('/health-check', [ServerStatusController::class, 'healthCheck']);
    // Include guest routes from guest.php
    include __DIR__ . '/Api/V1/guest.php';
    // Include authenticated routes from auth.php
    include __DIR__ . '/Api/V1/auth.php';
    // Include settings routes from settings.php
    include __DIR__ . '/Api/V1/settings.php';
});
