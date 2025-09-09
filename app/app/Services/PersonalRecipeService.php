<?php

namespace App\Services;

use App\Models\UserRecipes;
use Carbon\Carbon;

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
            return $this->supabase->select('recipes', [
                'id' => 'in.(' . $userRecipes->implode(',') . ')'
            ]);
        }

        // 2. Если на эту неделю нет → удаляем все старые подборки пользователя
        UserRecipes::query()->where('telegram_id', $telegramId)->delete();

        // 3. Берём все рецепты из Supabase
        $allRecipes = $this->supabase->select('recipes', ['select' => '*' ]);
        if (!$allRecipes) return [];

        // 4. Выбираем случайные n
        $selected = collect($allRecipes)->random(min($count, count($allRecipes)));

        // 5. Сохраняем новые записи
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

