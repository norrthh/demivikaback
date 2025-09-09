<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserWorkouts extends Model
{
    protected $fillable = [
        'telegram_id',
        'workout_id',
        'week_start',
    ];
}
