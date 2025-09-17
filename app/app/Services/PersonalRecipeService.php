<?php

namespace App\Services;

use App\Models\UserRegistration;
use Illuminate\Support\Collection;

class PersonalRecipeService
{
    protected SupabaseService $supabase;

    public function __construct(SupabaseService $supabase)
    {
        $this->supabase = $supabase;
    }

    public function getWeeklyRecipes($telegramId): array
    {
        $user = UserRegistration::query()
            ->where('telegram_id', $telegramId)
            ->first();

        if (!$user) {
            return [];
        }
        $week   = (new PersonalGroceryServices($this->supabase))->getWeek;

        $dietId = mb_strtolower(trim((string)($user->typeWeightLoss ?? ''))) === 'снижение веса' ? 1 : 2;
        $ppType = $user->diet === 'Без ограничений'
            ? 1
            : ($user->diet === 'Вегетарианство/веганство' ? 2 : 3);

        $recipes = collect($this->supabase->select('recipes_week', [
            'select'        => '*',
            'diet_goals_id' => "eq.$dietId",
//            'week'          => "eq.$week",
            'pp_type'       => "eq.$ppType",
        ]));


        // маппинг дней недели на русском
        $daysMap = [
            1 => 'Понедельник',
            2 => 'Вторник',
            3 => 'Среда',
            4 => 'Четверг',
            5 => 'Пятница',
            6 => 'Суббота',
            7 => 'Воскресенье',
        ];

        // маппинг meal_types → русский
        $mealMap = [
            'breakfast' => 'Завтрак',
            'snack'     => 'Перекус',
            'lunch'     => 'Обед',
            'smoothie'  => 'Смузи',
            'dinner'    => 'Ужин',
        ];

        $result = [];

        foreach ($daysMap as $dayNum => $dayName) {
            $dayRecipes = $recipes->where('day', $dayNum);

            // группировка по meal_types
            $grouped = $dayRecipes->groupBy(function ($item) {
                return $item['meal_types'][0] ?? 'other';
            });

            $result[$dayName] = [];

            foreach ($mealMap as $mealKey => $mealName) {
                $result[$dayName][$mealName] = $grouped->get($mealKey, collect())->values()->toArray();
            }
        }

        return $result;
    }
}
