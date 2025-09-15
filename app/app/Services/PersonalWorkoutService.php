<?php

namespace App\Services;

use App\Models\UserWorkouts;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PersonalWorkoutService
{
    private const TIMEZONE = 'Europe/Moscow';
    private const WEEK_START_DAY = Carbon::MONDAY;

    // Название категории, тренировки из которой нужно исключать целиком
    private const EXCLUDED_CATEGORY_NAME = 'Тренировки для спортзала';

    // true — исключать также ВСЕ дочерние категории исключённой
    // false — исключать только саму категорию по названию
    private const EXCLUDE_SUBTREE = false;

    public function __construct(
        protected SupabaseService $supabase
    ) {}

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
        $rows = collect($selectedIds)->map(fn (string $id) => [
            'telegram_id' => $telegramId,
            'workout_id'  => $id,
            'week_start'  => $weekStart,
            'created_at'  => $now,
            'updated_at'  => $now,
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
            if (is_array($row))  return (string)($row['id'] ?? '');
            if (is_object($row)) return (string)($row->id ?? '');
            return '';
        });

        return collect($ids)
            ->map(fn ($id) => $byId->get((string)$id))
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
            'id'     => "in.($in)",
        ]) ?? [];
    }

    /**
     * Кандидатные workout_id через таблицу-связку workout_to_category (M:N).
     * Логика:
     *  - Находим id категории по названию self::EXCLUDED_CATEGORY_NAME
     *  - (опц.) собираем все её дочерние id (если EXCLUDE_SUBTREE = true)
     *  - Берём ВСЕ категории, кроме исключённых => allowedCategoryIds
     *  - Кандидаты = workout_id, у которых есть связь с allowedCategoryIds
     *  - Если исключённые существуют: убираем все workout_id, у которых есть связь с excludedCategoryIds (строгий exclude)
     */
    private function fetchCandidateWorkoutIdsViaPivot(): Collection
    {
        // 1) Тянем словарь категорий
        $cats = $this->supabase->select('workout_categories', [
            'select' => 'id,name,parent_id',
            'order'  => 'created_at.asc',
        ]) ?? [];

        $allCategories = collect($cats)->map(function ($row) {
            return [
                'id'        => is_array($row) ? ($row['id'] ?? null) : (is_object($row) ? ($row->id ?? null) : null),
                'name'      => is_array($row) ? ($row['name'] ?? null) : (is_object($row) ? ($row->name ?? null) : null),
                'parent_id' => is_array($row) ? ($row['parent_id'] ?? null) : (is_object($row) ? ($row->parent_id ?? null) : null),
            ];
        })->filter(fn($c) => !empty($c['id']) && !empty($c['name']))->values();

        // 2) Определяем исключаемую категорию и, при необходимости, весь её поддерев
        $excludedRoot = $allCategories->firstWhere('name', self::EXCLUDED_CATEGORY_NAME);
        $excludedIds = collect();

        if ($excludedRoot) {
            $excludedIds = collect([$excludedRoot['id']]);
            if (self::EXCLUDE_SUBTREE) {
                // собираем всех потомков
                $childrenByParent = $allCategories->groupBy('parent_id');
                $queue = [$excludedRoot['id']];
                while (!empty($queue)) {
                    $pid = array_shift($queue);
                    foreach (($childrenByParent[$pid] ?? collect()) as $child) {
                        $cid = $child['id'];
                        if (!$excludedIds->contains($cid)) {
                            $excludedIds->push($cid);
                            $queue[] = $cid;
                        }
                    }
                }
            }
        }

        // 3) Разрешённые категории = все минус исключённые
        $allowedCategoryIds = $allCategories
            ->pluck('id')
            ->reject(fn ($id) => $excludedIds->contains($id))
            ->values();

        // Если категорий нет вообще — ничего не возвращаем
        if ($allowedCategoryIds->isEmpty() && $excludedIds->isEmpty()) {
            return collect(); // ничего не известно о категориях
        }

        // 4) Кандидаты по allowedCategoryIds (workout_to_category)
        $candidateIds = collect();
        if ($allowedCategoryIds->isNotEmpty()) {
            $allowedFilter = $this->buildInFilter($allowedCategoryIds);
            if ($allowedFilter) {
                $links = $this->supabase->select('workout_to_category', [
                    'select'       => 'workout_id,category_id',
                    'category_id'  => $allowedFilter,   // было "in(...)" — стало "in.(...)"
                ]) ?? [];

                $candidateIds = collect($links)->map(function ($row) {
                    if (is_array($row))  return $row['workout_id'] ?? null;
                    if (is_object($row)) return $row->workout_id ?? null;
                    return null;
                })->filter()->map('strval')->unique()->values();
            }
        }

        // 5) Жёстко исключаем тренировки, имеющие связь с excludedIds (если такие есть)
        // ✅ и участок с $blockedLinks:
        if ($excludedIds->isNotEmpty() && $candidateIds->isNotEmpty()) {
            $excludedFilter = $this->buildInFilter($excludedIds);
            if ($excludedFilter) {
                $blockedLinks = $this->supabase->select('workout_to_category', [
                    'select'       => 'workout_id,category_id',
                    'category_id'  => $excludedFilter, // было "in(...)" — стало "in.(...)"
                ]) ?? [];

                $blockedIds = collect($blockedLinks)->map(function ($row) {
                    if (is_array($row))  return $row['workout_id'] ?? null;
                    if (is_object($row)) return $row->workout_id ?? null;
                    return null;
                })->filter()->map('strval')->unique()->values();

                if ($blockedIds->isNotEmpty()) {
                    $blockedSet = $blockedIds->flip();
                    $candidateIds = $candidateIds->reject(fn ($id) => $blockedSet->has((string)$id))->values();
                }
            }
        }


        return $candidateIds;
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
