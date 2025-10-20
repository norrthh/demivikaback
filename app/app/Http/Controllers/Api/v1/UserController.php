<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\UserRegistration;
use App\Services\PersonalGroceryServices;
use App\Services\PersonalRecipeService;
use App\Services\PersonalWorkoutService;
use App\Services\SupabaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function getStatusRegistration(Request $request): JsonResponse
    {
        return response()->json([
            'status' => UserRegistration::query()->where('telegram_id', $request->get('telegram_id'))->exists(),
        ]);
    }

    public function registerAccount(Request $request): JsonResponse
    {
        $data = $request->all();
        $data['telegram_id'] = $request->get('telegramId');

        if (UserRegistration::query()->where('telegram_id', $request->get('telegramId'))->exists()) {
            return response()->json([
                'message' => 'Вы уже регистрировали аккаунт'
            ], 403);
        }

        UserRegistration::query()->create($data);

        return response()->json([
            'message' => 'Вы успешно зарегистрировали аккаунт'
        ]);
    }

    public function recipesPreview(Request $request, PersonalRecipeService $personalRecipeService, PersonalGroceryServices $groceryServices): JsonResponse
    {
        $request->validate([
            'telegram_id' => ['required', 'integer'],
            'week' => ['required', 'integer', 'min:1', 'max:4'],
        ]);

        $telegramId = $request->get('telegram_id');
        $week = $request->get('week');

        // Получаем рецепты для предварительного просмотра (без сохранения в БД)
        $recipes = $personalRecipeService->getPreviewRecipes($telegramId, $week);

        return response()->json([
            'recipes' => $recipes,
            'grocery' => $groceryServices->getPreview($telegramId, $week),
            'meta' => [
                'preview' => true,
                'week' => $week,
                'total' => count($recipes),
            ]
        ]);
    }

    public function recipes(Request $request, PersonalRecipeService $personalRecipeService, PersonalGroceryServices $groceryServices)
    {
        $request->validate([
            'telegram_id' => ['required', 'integer'],
        ]);

        $telegramId = $request->get('telegram_id');

        $limit = 7;
        $page = 1;

        // Получаем обычные рецепты пользователя (сохраненные в БД)
        $recipes = $personalRecipeService->getWeeklyRecipes($telegramId);
        $grocery = $groceryServices->get($telegramId);

        // Пагинация "вручную" (если нужно)
        $offset = ($page - 1) * $limit;
        $paginated = array_slice($recipes, $offset, $limit);

        return response()->json([
            'recipes' => $recipes,
            'grocery' => $grocery,
            'meta' => [
                'total' => count($recipes),
                'page'  => $page,
                'limit' => $limit,
            ]
        ]);
    }

    public function workouts(Request $request, PersonalWorkoutService $personalWorkoutService): JsonResponse
    {
        $request->validate([
            'telegram_id' => ['required', 'integer'],
        ]);

        $telegramId = $request->get('telegram_id');
        $limit = 7;
        $page = 1;

        $workouts = $personalWorkoutService->getWeeklyWorkouts($telegramId, $limit);

        // Пагинация "вручную" (если нужно)
        $offset = ($page - 1) * $limit;
        $paginated = array_slice($workouts, $offset, $limit);

        return response()->json([
            'workouts' => $paginated,
            'meta' => [
                'total' => count($workouts),
                'page'  => $page,
                'limit' => $limit,
            ]
        ]);
    }

    public function bindTelegramId(Request $request): JsonResponse
    {
        $request->validate([
            'telegram_id' => ['required', 'integer'],
            'user_data' => ['required', 'array'],
        ]);

        $telegramId = $request->get('telegram_id');
        $userData = $request->get('user_data');

        // Проверяем, есть ли уже пользователь с таким telegram_id
        $existingUser = UserRegistration::where('telegram_id', $telegramId)->first();

        if ($existingUser) {
            // Обновляем существующего пользователя
            $existingUser->update($userData);
            $message = 'Данные пользователя обновлены';
        } else {
            // Создаем нового пользователя
            $userData['telegram_id'] = $telegramId;
            UserRegistration::create($userData);
            $message = 'Пользователь успешно зарегистрирован';
        }

        return response()->json([
            'message' => $message,
            'telegram_id' => $telegramId,
            'status' => 'success'
        ]);
    }

    public function getUserByTelegramId(Request $request): JsonResponse
    {
        $request->validate([
            'telegram_id' => ['required', 'integer'],
        ]);

        $telegramId = $request->get('telegram_id');

        $user = UserRegistration::where('telegram_id', $telegramId)->first();

        if (!$user) {
            return response()->json([
                'message' => 'Пользователь не найден',
                'status' => 'not_found'
            ], 404);
        }

        return response()->json([
            'user' => $user,
            'status' => 'success'
        ]);
    }

    public function groccery(Request $request, PersonalGroceryServices $groceryServices): JsonResponse
    {
        $request->validate([
            'telegram_id' => ['required', 'integer'],
            'week' => ['nullable', 'integer', 'min:1', 'max:4'],
        ]);

        $telegramId = $request->get('telegram_id');
        $week = $request->get('week', 0);

        $grocery = $groceryServices->get($telegramId, $week);

        return response()->json([
            'grocery' => $grocery,
            'telegram_id' => $telegramId,
            'week' => $week
        ]);
    }

    public function adminRecipes()
    {
        return (new SupabaseService())->select('recipes_week');
    }
}
