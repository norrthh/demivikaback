<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserRecipes extends Model
{
    protected $fillable = [
        'telegram_id',
        'week',
        'date',
        'recipe_data',
    ];

    protected $casts = [
        'recipe_data' => 'array',
        'date' => 'date',
    ];
}
