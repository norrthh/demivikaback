<?php

namespace App\Services;

use App\Models\UserRegistration;
use App\Models\UserRecipes;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class PersonalRecipeService
{
    protected SupabaseService $supabase;

    public function __construct(SupabaseService $supabase)
    {
        $this->supabase = $supabase;
    }

    /**
     * Получает рецепты для предварительного просмотра без сохранения в БД
     */
    public function getPreviewRecipes($telegramId, int $week): array
    {
        $user = UserRegistration::query()
            ->where('telegram_id', $telegramId)
            ->first();

        if (!$user) {
            return [];
        }

        // Получаем неделю из цикла для Supabase
        $previewWeek = (new PersonalGroceryServices($this->supabase))->getWeek + $week;
        $previewWeek = $previewWeek > 3 ? (($previewWeek - 1) % 3) + 1 : $previewWeek;

        // Получаем данные из Supabase без сохранения в БД
        $dietId = mb_strtolower(trim((string)($user->typeWeightLoss ?? ''))) === 'снижение веса' ? 1 : 2;
        $ppType = $user->diet === 'Без ограничений'
            ? 1
            : ($user->diet === 'Вегетарианство/веганство' ? 2 : 3);

        $supabaseRecipes = collect($this->supabase->select('recipes_week', [
            'select' => '*',
            'diet_goals_id' => "eq.$dietId",
            'week' => "eq.$previewWeek",
            'pp_type' => "eq.$ppType",
        ]));

        // Формируем коллекцию для форматирования (имитируем структуру БД)
        $recipes = collect();
        $startDate = Carbon::today();

        foreach ($supabaseRecipes as $recipe) {
            $dayNumber = $recipe['day'] ?? 1;
            $date = $startDate->copy()->addDays($dayNumber - 1);

            $recipes->push([
                'telegram_id' => $telegramId,
                'week' => $previewWeek,
                'date' => $date,
                'recipe_data' => $recipe,
            ]);
        }

        return $this->formatRecipes($recipes);
    }

    public function getWeeklyRecipes($telegramId): array
    {
        $user = UserRegistration::query()
            ->where('telegram_id', $telegramId)
            ->first();

        if (!$user) {
            return [];
        }

        // Проверяем есть ли записи на сегодня
        $today = Carbon::today();

        $existingRecipes = UserRecipes::where('telegram_id', $telegramId)
            ->where('date', $today)
            ->exists();

        if (!$existingRecipes) {
            // Если нет записей на сегодня - генерируем новую неделю
            $this->generateNewWeekForUser($telegramId, $user);
        }

        // Получаем все рецепты пользователя и фильтруем до 7 записей
        $recipes = UserRecipes::where('telegram_id', $telegramId)
            ->orderBy('date', 'asc')
            ->get();

        // Берем только первые 7 записей (неделя)
        $recipes = $recipes->take(7);

        return $this->formatRecipes($recipes);
    }

    /**
     * Генерирует новую неделю для пользователя
     */
    private function generateNewWeekForUser($telegramId, $user): void
    {
        // Удаляем все старые записи для пользователя
        UserRecipes::where('telegram_id', $telegramId)->delete();

        // Получаем текущую неделю из цикла для Supabase
        $currentWeek = (new PersonalGroceryServices($this->supabase))->getWeek;
        $currentWeek = $currentWeek > 3 ? (($currentWeek - 1) % 3) + 1 : $currentWeek;

        // Получаем данные из Supabase
        $dietId = mb_strtolower(trim((string)($user->typeWeightLoss ?? ''))) === 'снижение веса' ? 1 : 2;
        $ppType = $user->diet === 'Без ограничений'
            ? 1
            : ($user->diet === 'Вегетарианство/веганство' ? 2 : 3);

        $supabaseRecipes = collect($this->supabase->select('recipes_week', [
            'select' => '*',
            'diet_goals_id' => "eq.$dietId",
            'week' => "eq.$currentWeek",
            'pp_type' => "eq.$ppType",
        ]));

        // Определяем дату начала недели (сегодня)
        $weekStartDate = Carbon::today();

        foreach ($supabaseRecipes as $recipe) {
            $dayNumber = $recipe['day'] ?? 1;
            $date = $weekStartDate->copy()->addDays($dayNumber - 1);

            // Используем updateOrCreate для избежания дубликатов
            UserRecipes::updateOrCreate(
                [
                    'telegram_id' => $telegramId,
                    'week' => $currentWeek,
                    'date' => $date,
                ],
                [
                    'recipe_data' => $recipe,
                ]
            );
        }
    }

    private function formatRecipes(Collection $recipes): array
    {
        // маппинг дней недели на русском
        $daysMap = [
            1 => '1 день',
            2 => '2 день',
            3 => '3 день',
            4 => '4 день',
            5 => '5 день',
            6 => '6 день',
            7 => '7 день',
        ];

        // маппинг meal_types → русский
        $mealMap = [
            'breakfast' => 'Завтрак (6.30-10.30)',
            'snack' => 'Перекус (10.30-12.30):',
            'lunch' => 'Обед (12.30-15.30)',
            'smoothie' => 'Смузи',
            'dinner' => 'Ужин (17.30-19.00)',
        ];

        $result = [];

        foreach ($daysMap as $dayNum => $dayName) {
            $dayRecipes = $recipes->where('recipe_data.day', $dayNum);

            // группировка по meal_types
            $grouped = $dayRecipes->groupBy(function ($item) {
                $mealTypes = $item['recipe_data']['meal_types'] ?? [];
                return $mealTypes[0] ?? 'other';
            });

            $result[$dayName] = [];

            foreach ($mealMap as $mealKey => $mealName) {
                $mealRecipes = $grouped->get($mealKey, collect())->map(function ($item) {
                    return $item['recipe_data'];
                })->values()->toArray();

                $result[$dayName][$mealName] = $mealRecipes;
            }
        }

        return $result;
    }
}
