<?php

namespace App\Services;

use App\Models\UserRegistration;

class PersonalGroceryServices
{
    public int $getWeek;
    protected SupabaseService $supabase;

    public function __construct(SupabaseService $supabase)
    {
        $week = now()->format('W');
        $this->getWeek = $week == 38 ? 1 : ($week == 39 ? 2 : ($week == 40 ? 3 : ($week == 41 ? 4 : $week)));
        $this->supabase = $supabase;
    }

    public function get($telegramId)
    {
        $user = UserRegistration::query()
            ->where('telegram_id', $telegramId)
            ->first();

        if (!$user) {
            return [];
        }

        $type = mb_strtolower(trim((string)($user->typeWeightLoss ?? '')));
        $dietId = $type === 'похудение' ? 1 : 2;

        return $this->supabase->select('grocery_items', [
            'select' => '*',
            'diet_goals_id' => "eq.$dietId",
            'week' => "eq." . $this->getWeek,
            'order' => 'created_at.asc',
        ]);

    }
}
