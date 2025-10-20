<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'order_num',
        'telegram_id',
        'type',
        'product_name',
        'amount',
        'status',
        'payment_url',
        'prodamus_data',
        'paid_at',
    ];

    protected $casts = [
        'prodamus_data' => 'array',
        'paid_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    /**
     * Создает новый платеж или возвращает существующий
     */
    public static function createPayment(array $data): self
    {
        // Проверяем, существует ли уже платеж с таким order_id
        $existingPayment = self::query()->where('order_id', $data['order_id'])->first();

        if ($existingPayment) {
            // Если платеж уже существует, возвращаем его
            return $existingPayment;
        }

        // Создаем новый платеж
        return self::create($data);
    }

    /**
     * Обновляет статус платежа
     */
    public function updateStatus(string $status, array $prodamusData = null): void
    {
        $this->update([
            'status' => $status,
            'prodamus_data' => $prodamusData,
            'paid_at' => $status === 'paid' ? now() : null,
        ]);
    }

    /**
     * Проверяет, оплачен ли платеж
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Получает платежи пользователя
     */
    public static function getUserPayments(int $telegramId): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('telegram_id', $telegramId)
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
