<?php

use App\Http\Controllers\Api\v1\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('/v1')->group(function () {
    Route::post('getStatusRegistration', [UserController::class, 'getStatusRegistration']);
    Route::post('registerAccount', [UserController::class, 'registerAccount']);

    Route::get('recipes', [UserController::class, 'recipes']);
    Route::get('workouts', [UserController::class, 'workouts']);
});
