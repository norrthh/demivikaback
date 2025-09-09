<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserRecipes extends Model
{
    protected $fillable = [
        'telegram_id',
        'recipe_id',
        'week_start',
    ];
}
