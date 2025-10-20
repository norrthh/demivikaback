<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DemivikaService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.demivika.base_url');
        $this->apiKey = config('services.demivika.api_key');
    }

    /**
     * Предоставляет доступ пользователю в основной системе DemiVika
     */
    public function grantAccess(int $telegramId): bool
    {
        try {
            // Вычисляем дату окончания доступа
            $accessUntil = now()->addWeeks(3)->format('Y-m-d');

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . 'api/admin/grant-access', [
                'telegram_id' => $telegramId,
                'access_until' => $accessUntil,
            ]);

            if ($response->successful()) {
                Log::info('Access granted successfully', [
                    'telegram_id' => $telegramId,
                    'access_until' => $accessUntil,
                    'response' => $response->json()
                ]);
                return true;
            }

            Log::error('Failed to grant access', [
                'telegram_id' => $telegramId,
                'access_until' => $accessUntil,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error('Exception while granting access', [
                'telegram_id' => $telegramId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Проверяет статус пользователя в основной системе
     */
    public function getUserStatus(int $telegramId): ?array
    {
        try {
            $response = Http::get($this->baseUrl . 'api/user', [
                'telegram_id' => $telegramId,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Exception while getting user status', [
                'telegram_id' => $telegramId,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }
}
