<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\Hmac;

class ProdamusService
{
    private string $baseUrl;
    private string $secretKey;
    private string $payformUrl;

    // Константы для клуба "КОД ЖЕНЩИНЫ"
    public const SUBSCRIPTION_AMOUNT = 2990.00;
    public const SUBSCRIPTION_DESCRIPTION = 'Доступ к клубу на 1 месяц КОД ЖЕНЩИНЫ';

    public function __construct()
    {
        $this->baseUrl = config('services.prodamus.base_url');
        $this->secretKey = config('services.prodamus.secret_key');
        $this->payformUrl = config('services.prodamus.payform_url');
    }

    /**
     * Создает платежную ссылку
     */
    public function createPaymentLink(array $data): string
    {
//        $data['signature'] = Hmac::create($data, $this->secretKey);

        // Формируем ссылку с правильным форматом массивов
        $queryString = $this->buildQueryString($data);
        return $this->payformUrl . '?' . $queryString;
    }

    /**
     * Создает ссылку для получения ссылки на оплату
     */
    public function createPaymentLinkRequest(array $data): string
    {
        $data = array_merge([
            'do' => 'link',
        ], $data);

        // Добавляем подпись
        $data['signature'] = Hmac::create($data, $this->secretKey);

        // Отправляем запрос
        $response = Http::get($this->baseUrl, $data);

        if ($response->successful()) {
            return $response->body();
        }

        Log::error('Prodamus API error', [
            'status' => $response->status(),
            'body' => $response->body(),
            'data' => $data
        ]);

        throw new \Exception('Failed to create payment link');
    }

    /**
     * Проверяет подпись входящего webhook
     */
    public function verifySignature(array $data, string $signature): bool
    {
        return Hmac::verify($data, $this->secretKey, $signature);
    }

    /**
     * Создает подпись для данных
     */
    private function createSignature(array $data): string
    {
        // Удаляем подпись из данных для создания подписи
        unset($data['signature']);

        // Сортируем данные по ключам
        ksort($data);

        // Формируем строку для подписи согласно документации Prodamus
        $signatureString = '';
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // Для массивов используем специальный формат
                foreach ($value as $subKey => $subValue) {
                    if (is_array($subValue)) {
                        // Для вложенных массивов (например, products[0][name])
                        foreach ($subValue as $subSubKey => $subSubValue) {
                            $signatureString .= $key . '[' . $subKey . '][' . $subSubKey . ']=' . urlencode($subSubValue) . '&';
                        }
                    } else {
                        // Для обычных массивов (например, products[0])
                        $signatureString .= $key . '[' . $subKey . ']=' . urlencode($subValue) . '&';
                    }
                }
            } else {
                // Для обычных значений
                $signatureString .= $key . '=' . urlencode($value) . '&';
            }
        }

        // Убираем последний &
        $signatureString = rtrim($signatureString, '&');

        Log::info('Signature string for Prodamus', [
            'string' => $signatureString,
            'secret_key_length' => strlen($this->secretKey)
        ]);

        // Создаем HMAC подпись
        return hash_hmac('sha256', $signatureString, $this->secretKey);
    }

    /**
     * Создает правильную строку запроса с поддержкой массивов
     */
    private function buildQueryString(array $data): string
    {
        $queryParts = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // Обрабатываем массив products
                foreach ($value as $index => $item) {
                    if (is_array($item)) {
                        // Для вложенных массивов (например, products[0][name])
                        foreach ($item as $subKey => $subValue) {
                            $queryParts[] = $key . '[' . $index . '][' . $subKey . ']=' . $this->encodeValue($subValue);
                        }
                    } else {
                        // Для обычных массивов
                        $queryParts[] = $key . '[' . $index . ']=' . $this->encodeValue($item);
                    }
                }
            } else {
                // Для обычных значений
                $queryParts[] = $key . '=' . $this->encodeValue($value);
            }
        }

        return implode('&', $queryParts);
    }

    /**
     * Правильно кодирует значение для URL
     */
    private function encodeValue($value): string
    {
        // Для русских символов используем rawurlencode только для специальных символов
        // но оставляем кириллицу, пробелы и кавычки читаемыми
        if (is_string($value)) {
            // Кодируем только амперсанды, знаки равенства и плюсы
            return str_replace(['&', '=', '+'], ['%26', '%3D', '%2B'], $value);
        }

        return (string) $value;
    }

    /**
     * Создает данные для разового платежа
     */
    public function createOneTimePayment(int $telegramId): array
    {
        return [
            'order_id' => 'pay_' . $telegramId . '_' . time(),
            'customer_phone' => '1241241212',
            'products' => [
                [
                    'name' => self::SUBSCRIPTION_DESCRIPTION,
                    'price' => self::SUBSCRIPTION_AMOUNT,
                    'quantity' => 1,
                ]
            ],
            'customer_extra' => self::SUBSCRIPTION_DESCRIPTION,
            'do' => 'pay'
        ];
    }

}
