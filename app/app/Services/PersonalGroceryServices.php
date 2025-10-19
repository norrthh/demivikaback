<?php

namespace App\Services;

use App\Models\UserRegistration;
use App\Models\UserRecipes;
use Illuminate\Support\Facades\Log;

class PersonalGroceryServices
{
    public int $getWeek;
    protected SupabaseService $supabase;

    public function __construct(SupabaseService $supabase)
    {
        $week = now()->format('W');
        $this->getWeek = (($week - 39 - 1) % 3) + 1;
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

        // Получаем неделю из сохраненных рецептов пользователя
        $userRecipe = UserRecipes::where('telegram_id', $telegramId)
            ->orderBy('date', 'asc')
            ->first();

        if (!$userRecipe) {
            return [];
        }

        $savedWeek = $userRecipe->week;

        $type = mb_strtolower(trim((string)($user->typeWeightLoss ?? '')));
        $dietId = $type === 'Снижение веса' ? 1 : 2;
        $ppType = $user->diet === 'Без ограничений'
            ? 1
            : ($user->diet === 'Вегетарианство/веганство' ? 2 : 3);

        Log::info("Using saved week: $savedWeek for user: $telegramId");

        return $this->supabase->select('grocery_items', [
            'select' => '*',
            'diet_goals_id' => "eq.$dietId",
            'week' => "eq.$savedWeek",
            'pp_type' => "eq.$ppType",
        ]);
    }

    public function getPreview($telegramId, int $week)
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

        // Рассчитываем неделю с циклическим переходом (1-3)
        $targetWeek = $this->getWeek + $week;
        $targetWeek = $targetWeek > 3 ? (($targetWeek - 1) % 3) + 1 : $targetWeek;

        Log::info("Preview requested week: $week, Target week: $targetWeek");

        return $this->supabase->select('grocery_items', [
            'select' => '*',
            'diet_goals_id' => "eq.$dietId",
            'week' => "eq.$targetWeek",
            'pp_type' => "eq.$ppType",
        ]);
    }
}