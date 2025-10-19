<?php

use App\Http\Controllers\Api\v1\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('/v1')->group(function () {
    // Регистрация и статус
    Route::post('getStatusRegistration', [UserController::class, 'getStatusRegistration']);
    Route::post('registerAccount', [UserController::class, 'registerAccount']);

    // Рецепты, тренировки и продукты
    Route::get('recipes', [UserController::class, 'recipes']);
    Route::get('recipes/preview', [UserController::class, 'recipesPreview']);

    Route::get('workouts', [UserController::class, 'workouts']);
    Route::get('groccery', [UserController::class, 'groccery']);
});
