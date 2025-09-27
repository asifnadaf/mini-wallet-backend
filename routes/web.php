<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\ServerStatusController;

Route::get('/', [ServerStatusController::class, 'healthCheck']);
