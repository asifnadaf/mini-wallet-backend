<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\ServerStatusController;

Route::group(['prefix' => 'v1', 'as' => 'api.'], function () {
    Route::get('/health-check', [ServerStatusController::class, 'healthCheck']);
    // Include guest routes from guest.php
    include __DIR__ . '/Api/V1/guest.php';
    // Include email_verification routes from email_verification.php
    include __DIR__ . '/Api/V1/email_verification.php';
    // Include settings routes from settings.php
    include __DIR__ . '/Api/V1/settings.php';
});
