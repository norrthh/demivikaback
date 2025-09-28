<?php

namespace App\Services;

use App\Models\UserWorkouts;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PersonalWorkoutService
{
    private const TIMEZONE = 'Europe/Moscow';
    private const WEEK_START_DAY = CarbonInterface::MONDAY;

    // Название категории, тренировки из которой нужно исключать целиком
    private const EXCLUDED_CATEGORY_NAME = 'Тренировки для спортзала';

    // true — исключать также ВСЕ дочерние категории исключённой
    // false — исключать только саму категорию по названию
    private const EXCLUDE_SUBTREE = false;

    public function __construct(
        protected SupabaseService $supabase
    )
    {
    }

    /**
     * Возвращает (и при необходимости генерирует) подборку на неделю.
     * Сохраняет в MySQL таблицу user_workouts ровно workout_id.
     */
    public function getWeeklyWorkouts(int $telegramId, int $count = 5): array
    {
        $weekStart = Carbon::now(self::TIMEZONE)
            ->startOfWeek(self::WEEK_START_DAY)
            ->toDateString();

        // 1) Если подборка на эту неделю уже есть — возвращаем
        $savedIds = UserWorkouts::query()
            ->where('telegram_id', $telegramId)
            ->where('week_start', $weekStart)
            ->pluck('workout_id')
            ->all();

        if (!empty($savedIds)) {
            return $this->orderedWorkouts($savedIds);
        }

        // 2) Иначе — чистим прошлые недели
        UserWorkouts::query()->where('telegram_id', $telegramId)->delete();

        // 3) Получаем кандидатов через M:N связку workout_to_category
        $candidateIds = $this->fetchCandidateWorkoutIdsViaPivot();

        if ($candidateIds->isEmpty()) {
            Log::warning('No candidate workouts after M:N filtering.');
            return [];
        }

        // 4) Случайно выбираем N id
        $selectedIds = $candidateIds
            ->shuffle()
            ->take(min($count, $candidateIds->count()))
            ->values()
            ->all();

        // 5) Атомарная запись (upsert) — защищает от дублей
        $now = Carbon::now();
        $rows = collect($selectedIds)->map(fn(string $id) => [
            'telegram_id' => $telegramId,
            'workout_id' => $id,
            'week_start' => $weekStart,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        UserWorkouts::upsert(
            $rows,
            ['telegram_id', 'week_start', 'workout_id'],
            ['updated_at']
        );

        // 6) Возвращаем карточки в выбранном порядке
        return $this->orderedWorkouts($selectedIds);
    }

    /**
     * Возвращает workouts в ТОЧНО заданном порядке $ids.
     */
    private function orderedWorkouts(array $ids): array
    {
        if (empty($ids)) return [];

        $list = $this->fetchWorkoutsByIds($ids);
        $byId = collect($list)->keyBy(function ($row) {
            if (is_array($row)) return (string)($row['id'] ?? '');
            if (is_object($row)) return (string)($row->id ?? '');
            return '';
        });

        return collect($ids)
            ->map(fn($id) => $byId->get((string)$id))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Детали тренировок по id.
     * Подстрой набор полей под фронт.
     */
    private function fetchWorkoutsByIds(array $ids): array
    {
        if (empty($ids)) return [];

        $in = implode(',', array_map('strval', $ids));

        return $this->supabase->select('workouts', [
            'select' => 'id,name,cover_image_url,kinescope_id,duration_minutes,description,inventory_description,created_at',
            'id' => "in.($in)",
        ]) ?? [];
    }

    private function fetchCandidateWorkoutIdsViaPivot(): Collection
    {
        $getFilets = [
            '17738d8f-9162-48b5-a102-586dbe1808f0',
            'f1e8fcd8-066c-4c19-a1f0-4f1bbfa38dcd',
            '18c78f94-ccd1-42b0-9ea3-f01623fd5a1d'
        ];

        $filter = $this->buildInFilter($getFilets);
        if (!$filter) {
            return collect();
        }

        $links = $this->supabase->select('workout_to_category', [
            'select' => 'workout_id,category_id',
            'category_id' => $filter,
        ]) ?? [];

        shuffle($links);

        return collect($links)
            ->map(fn($row) => is_array($row) ? $row['workout_id'] : $row->workout_id)
            ->filter()
            ->map('strval')
            ->unique()
            ->values();
    }


    // ✅ добавь в класс helper
    private function buildInFilter($values): ?string
    {
        $vals = collect($values)
            ->filter(fn($v) => filled($v))
            ->map(fn($v) => (string)$v)
            ->unique()
            ->values()
            ->all();

        if (count($vals) === 0) {
            return null;
        }

        // Для UUID кавычки не нужны. Важно оставить точку после in
        return 'in.(' . implode(',', $vals) . ')';
    }

}
