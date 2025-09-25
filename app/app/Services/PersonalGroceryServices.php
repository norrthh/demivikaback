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
        $this->getWeek = $week == 39 ? 1 : ($week == 40 ? 2 : ($week == 41 ? 3 : ($week == 42 ? 4 : $week)));
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
        $dietId = $type === 'Снижение веса' ? 1 : 2;
        $ppType = $user->diet === 'Без ограничений'
            ? 1
            : ($user->diet === 'Вегетарианство/веганство' ? 2 : 3);

        return $this->supabase->select('grocery_items', [
            'select' => '*',
            'diet_goals_id' => "eq.$dietId",
            'week' => "eq." . $this->getWeek,
            'order' => 'created_at.asc',
            'pp_type'       => "eq.$ppType",
        ]);
    }
}
