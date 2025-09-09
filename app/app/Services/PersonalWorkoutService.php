<?php

namespace App\Services;

use App\Models\UserWorkouts;
use Carbon\Carbon;

class PersonalWorkoutService
{
    protected SupabaseService $supabase;

    public function __construct(SupabaseService $supabase)
    {
        $this->supabase = $supabase;
    }

    public function getWeeklyWorkouts(int $telegramId, int $count = 5)
    {
        $weekStart = Carbon::now()->startOfWeek()->toDateString();

        // 1. Проверяем, есть ли подборка на эту неделю
        $userWorkouts = UserWorkouts::query()->where('telegram_id', $telegramId)
            ->where('week_start', $weekStart)
            ->pluck('workout_id');

        if ($userWorkouts->isNotEmpty()) {
            // Есть подборка → просто подтягиваем рецепты из Supabase
            return $this->supabase->select('workouts', [
                'id' => 'in.(' . $userWorkouts->implode(',') . ')'
            ]);
        }

        // 2. Если на эту неделю нет → удаляем все старые подборки пользователя
        UserWorkouts::query()->where('telegram_id', $telegramId)->delete();

        // 3. Берём все рецепты из Supabase
        $allWorkouts = $this->supabase->select('workouts', ['select' => '*' ]);

        if (!$allWorkouts) return [];

        // 4. Выбираем случайные n
        $selected = collect($allWorkouts)->random(min($count, count($allWorkouts)));

        // 5. Сохраняем новые записи
        foreach ($selected as $recipe) {
            UserWorkouts::query()->create([
                'telegram_id' => $telegramId,
                'workout_id'   => $recipe['id'],
                'week_start'  => $weekStart,
            ]);
        }

        return $selected->toArray();
    }
}

