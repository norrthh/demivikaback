<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return (new \App\Services\SupabaseService())->select('workouts');
});
