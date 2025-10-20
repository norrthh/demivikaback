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
        'typeWeightLoss',
        'subscription_active',
        'subscription_expires_at'
    ];

    protected $casts = [
        'subscription_active' => 'boolean',
        'subscription_expires_at' => 'datetime',
    ];

    /**
     * Активирует подписку пользователя
     */
    public function activateSubscription(int $months = 1): void
    {
        $this->update([
            'subscription_active' => true,
            'subscription_expires_at' => now()->addMonths($months),
        ]);
    }

    /**
     * Проверяет, активна ли подписка
     */
    public function hasActiveSubscription(): bool
    {
        return $this->subscription_active && 
               $this->subscription_expires_at && 
               $this->subscription_expires_at->isFuture();
    }
}
