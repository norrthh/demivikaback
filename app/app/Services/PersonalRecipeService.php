<?php

namespace App\Services;

use App\Models\UserRecipes;
use App\Models\UserRegistration;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PersonalRecipeService
{
    protected SupabaseService $supabase;

    public function __construct(SupabaseService $supabase)
    {
        $this->supabase = $supabase;
    }

    public function getWeeklyRecipes(int $telegramId, int $count = 5)
    {
        $weekStart = Carbon::now()->startOfWeek()->toDateString();

        // 1. Проверяем, есть ли подборка на эту неделю
        $userRecipes = UserRecipes::query()->where('telegram_id', $telegramId)
            ->where('week_start', $weekStart)
            ->pluck('recipe_id');

        if ($userRecipes->isNotEmpty()) {
            // Есть подборка → просто подтягиваем рецепты из Supabase
            return $this->supabase->select('recipes_week', [
                'id' => 'in.(' . $userRecipes->implode(',') . ')'
            ]);
        }

        // 2. Если на эту неделю нет → удаляем все старые подборки пользователя
        UserRecipes::query()->where('telegram_id', $telegramId)->delete();
        $user = UserRegistration::query()
            ->where('telegram_id', $telegramId)
            ->first();

        if (!$user) {
            return [];
        }

        $type = mb_strtolower(trim((string)($user->typeWeightLoss ?? '')));

        $dietId = $type === 'Снижение веса' ? 1 : 2;
        $week = (new PersonalGroceryServices($this->supabase))->getWeek;
        $pp_type = $user->diet === 'Без ограничений' ? 1 : ($user->diet === 'Вегетарианство/веганство' ? 2 : 3);

        // 3. Берём все рецепты для диеты/недели
        $allRecipes = collect($this->supabase->select('recipes_week', [
            'select' => '*',
            'diet_goals_id'  => "eq.$dietId",
            'week' => "eq." . $week,
            'pp_type' => "eq." . $pp_type,
        ]));

// 4. Группируем по meal_types
        $grouped = $allRecipes->groupBy(function ($item) {
            return $item['meal_types'][0] ?? null; // берём первый тип
        });

        $order = ['breakfast', 'snack', 'lunch', 'dinner'];
        $selected = collect();

        foreach ($order as $meal) {
            if (!empty($grouped[$meal])) {
                // есть такие блюда → берём случайное
                $selected->push(collect($grouped[$meal])->random());
            } else {
                // нет блюд этого типа → берём случайное из всего списка
                if ($allRecipes->isNotEmpty()) {
                    $selected->push($allRecipes->random());
                }
            }
        }

        foreach ($selected as $recipe) {
            UserRecipes::query()->create([
                'telegram_id' => $telegramId,
                'recipe_id'   => $recipe['id'],
                'week_start'  => $weekStart,
            ]);
        }

        return $selected->toArray();
    }
}

