<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserRegistration extends Model
{
    protected $fillable = [
        'telegram_id',
        'height',
        'weight',
        'goal',
        'fitness',
        'diet',
        'time',
        'motivation',
    ];
}
