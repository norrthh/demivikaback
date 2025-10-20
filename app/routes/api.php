<?php

use App\Http\Controllers\Api\v1\UserController;
use App\Http\Controllers\Api\v1\PaymentController;
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

    // Платежи
    Route::post('payment/create', [PaymentController::class, 'createPayment']);
    Route::get('payment/history', [PaymentController::class, 'getUserPayments']);
    Route::get('payment/subscription-status', [PaymentController::class, 'getSubscriptionStatus']);
    Route::post('payment/webhook', [PaymentController::class, 'webhook']);
});
