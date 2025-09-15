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

    public function recipes(Request $request, PersonalRecipeService $personalRecipeService, PersonalGroceryServices $groceryServices)
    {
        $telegramId = $request->get('telegram_id');

        $limit = 7;
        $page = 1;

        $recipes = $personalRecipeService->getWeeklyRecipes($telegramId, $limit);

        // Пагинация "вручную" (если нужно)
        $offset = ($page - 1) * $limit;
        $paginated = array_slice($recipes, $offset, $limit);

        return response()->json([
            'recipes' => $paginated,
            'grocery' => $groceryServices->get($telegramId),
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

        $recipes = $personalWorkoutService->getWeeklyWorkouts($telegramId, $limit);

        // Пагинация "вручную" (если нужно)
        $offset = ($page - 1) * $limit;
        $paginated = array_slice($recipes, $offset, $limit);

        return response()->json([
            'workouts' => $paginated,
            'meta' => [
                'total' => count($recipes),
                'page'  => $page,
                'limit' => $limit,
            ]
        ]);
    }
}
