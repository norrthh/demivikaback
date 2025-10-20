<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class Hmac
{
    /**
     * Создает подпись для данных согласно документации Prodamus
     */
    public static function create(array $data, string $secretKey): string
    {
        // Удаляем подпись из данных для создания подписи
        unset($data['signature']);

        // Сортируем данные по ключам
        ksort($data);

        // Формируем строку для подписи согласно документации Prodamus
        $signatureString = '';
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $subKey => $subValue) {
                    if (is_array($subValue)) {
                        foreach ($subValue as $subSubKey => $subSubValue) {
                            $signatureString .= $key . '[' . $subKey . '][' . $subSubKey . ']=' . urlencode($subSubValue) . '&';
                        }
                    } else {
                        $signatureString .= $key . '[' . $subKey . ']=' . urlencode($subValue) . '&';
                    }
                }
            } else {
                $signatureString .= $key . '=' . urlencode($value) . '&';
            }
        }

        // Убираем последний &
        $signatureString = rtrim($signatureString, '&');

        Log::info('Hmac signature string', [
            'string' => $signatureString,
            'secret_key_length' => strlen($secretKey)
        ]);

        // Создаем HMAC подпись
        return hash_hmac('sha256', $signatureString, $secretKey);
    }

    /**
     * Проверяет подпись
     */
    public static function verify(array $data, string $secretKey, string $signature): bool
    {
        $expectedSignature = self::create($data, $secretKey);
        return hash_equals($expectedSignature, $signature);
    }
}
