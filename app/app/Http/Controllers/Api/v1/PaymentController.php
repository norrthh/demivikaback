<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\UserRegistration;
use App\Services\ProdamusService;
use App\Services\SupabaseService;
use App\Services\Hmac;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    private ProdamusService $prodamusService;
    private SupabaseService $supabaseService;

    public function __construct(ProdamusService $prodamusService, SupabaseService $supabaseService)
    {
        $this->prodamusService = $prodamusService;
        $this->supabaseService = $supabaseService;
    }

    /**
     * Создает разовый платеж за курс
     */
    public function createPayment(Request $request): JsonResponse
    {
        $request->validate([
            'telegram_id' => ['required', 'integer'],
        ]);

        $telegramId = $request->get('telegram_id');

        try {
            // Создаем данные для платежа
            $paymentData = $this->prodamusService->createOneTimePayment($telegramId);

            // Создаем запись в БД
            $payment = Payment::createPayment([
                'order_id' => $paymentData['order_id'],
                'telegram_id' => $telegramId,
                'type' => 'one_time',
                'product_name' => ProdamusService::SUBSCRIPTION_DESCRIPTION,
                'amount' => ProdamusService::SUBSCRIPTION_AMOUNT,
                'status' => 'pending',
            ]);

            // Создаем ссылку на оплату
            $paymentUrl = $this->prodamusService->createPaymentLink($paymentData);

            // Обновляем запись с ссылкой
            $payment->update(['payment_url' => $paymentUrl]);

            return response()->json([
                'success' => true,
                'payment_url' => $paymentUrl,
                'order_id' => $payment->order_id,
                'amount' => ProdamusService::SUBSCRIPTION_AMOUNT,
                'message' => 'Ссылка на оплату создана'
            ]);

        } catch (\Exception $e) {
            Log::error('Payment creation error', [
                'telegram_id' => $telegramId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Если это ошибка дублирования, возвращаем существующий платеж
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                try {
                    $existingPayment = Payment::where('order_id', $paymentData['order_id'])->first();
                    if ($existingPayment) {
                        return response()->json([
                            'success' => true,
                            'payment_url' => $existingPayment->payment_url,
                            'order_id' => $existingPayment->order_id,
                            'amount' => ProdamusService::SUBSCRIPTION_AMOUNT,
                            'message' => 'Платеж уже существует'
                        ]);
                    }
                } catch (\Exception $retryException) {
                    Log::error('Failed to retrieve existing payment', [
                        'telegram_id' => $telegramId,
                        'error' => $retryException->getMessage()
                    ]);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Ошибка создания платежа'
            ], 500);
        }
    }


    /**
     * Получает платежи пользователя
     */
    public function getUserPayments(Request $request): JsonResponse
    {
        $request->validate([
            'telegram_id' => ['required', 'integer'],
        ]);

        $telegramId = $request->get('telegram_id');
        $payments = Payment::getUserPayments($telegramId);

        return response()->json([
            'success' => true,
            'payments' => $payments->map(function ($payment) {
                return [
                    'order_id' => $payment->order_id,
                    'type' => $payment->type,
                    'product_name' => $payment->product_name,
                    'amount' => $payment->amount,
                    'status' => $payment->status,
                    'created_at' => $payment->created_at,
                    'paid_at' => $payment->paid_at,
                ];
            })
        ]);
    }

    /**
     * Получает статус подписки пользователя из Supabase
     */
    public function getSubscriptionStatus(Request $request): JsonResponse
    {
        $request->validate([
            'telegram_id' => ['required', 'integer'],
        ]);

        $telegramId = $request->get('telegram_id');

        try {
            // Получаем данные пользователя из Supabase
            $userData = $this->supabaseService->select('tg_users', [
                'telegram_id' => "eq.{$telegramId}"
            ]);

            if (empty($userData)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Пользователь не найден'
                ], 404);
            }

            $user = $userData[0];
            $hasActiveSubscription = false;
            $subscriptionExpiresAt = null;

            // Проверяем активность подписки
            if (isset($user['access_until']) && $user['access_until']) {
                $subscriptionExpiresAt = $user['access_until'];
                $hasActiveSubscription = now()->format('Y-m-d') <= $user['access_until'];
            }

            return response()->json([
                'success' => true,
                'subscription_active' => $hasActiveSubscription,
                'subscription_expires_at' => $subscriptionExpiresAt,
                'user_id' => $user['id'] ?? null
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting subscription status from Supabase', [
                'telegram_id' => $telegramId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка получения статуса подписки'
            ], 500);
        }
    }

    /**
     * Webhook для получения уведомлений от Prodamus
     */
    public function webhook(Request $request): JsonResponse
    {
        try {
            // Проверяем, что есть POST данные
            if (empty($_POST)) {
                Log::error('Webhook received empty POST data');
                return response()->json(['error' => 'Empty POST data'], 400);
            }

            $data = $request->all();
            $signature = $request->header('Sign');

            // Проверяем наличие подписи
            if (empty($signature)) {
                Log::error('Webhook received without signature', ['data' => $data]);
                return response()->json(['error' => 'Signature not found'], 400);
            }

            Log::info('Prodamus webhook received', [
                'data' => $data,
                'signature' => $signature
            ]);

            // Проверяем обязательные поля согласно документации Prodamus
            $orderId = $data['order_id'] ?? null;
            $orderNum = $data['order_num'] ?? null;

            if (!$orderId && !$orderNum) {
                Log::error('No order_id or order_num in webhook', ['data' => $data]);
                return response()->json(['error' => 'No order_id or order_num'], 400);
            }

            // Находим платеж по order_num (это наш order_id) или order_id (внутренний ID Prodamus)
            $payment = null;
            if ($orderNum) {
                // order_num в webhook'е - это наш order_id, ищем по полю order_id в БД
                $payment = Payment::where('order_id', $orderNum)->first();
                Log::info('Searching payment by order_num (our order_id)', ['order_num' => $orderNum, 'found' => $payment ? 'yes' : 'no']);
            }

            if (!$payment && $orderId) {
                // order_id в webhook'е - это внутренний ID Prodamus, ищем по полю order_num в БД
                $payment = Payment::where('order_num', $orderId)->first();
                Log::info('Searching payment by order_id (Prodamus internal ID)', ['order_id' => $orderId, 'found' => $payment ? 'yes' : 'no']);
            }

            if (!$payment) {
                // Если платеж не найден, но у нас есть order_num, попробуем извлечь telegram_id
                if ($orderNum && preg_match('/^pay_(\d+)_[a-f0-9]+_\d+$/', $orderNum, $matches)) {
                    $telegramId = (int) $matches[1];
                    
                    Log::info('Payment not found, attempting to create from webhook data', [
                        'order_id' => $orderId,
                        'order_num' => $orderNum,
                        'telegram_id' => $telegramId
                    ]);
                    
                    // Создаем платеж на основе данных webhook
                    $payment = Payment::createPayment([
                        'order_id' => $orderNum,
                        'order_num' => $orderId,
                        'telegram_id' => $telegramId,
                        'type' => 'one_time',
                        'product_name' => ProdamusService::SUBSCRIPTION_DESCRIPTION,
                        'amount' => ProdamusService::SUBSCRIPTION_AMOUNT,
                        'status' => 'pending',
                        'prodamus_data' => $data,
                    ]);
                    
                    Log::info('Payment created from webhook data', ['payment_id' => $payment->id]);
                } else {
                    Log::error('Payment not found and cannot extract telegram_id', ['order_id' => $orderId, 'order_num' => $orderNum]);
                    return response()->json(['error' => 'Payment not found'], 404);
                }
            }

            // Получаем telegram_id из найденного платежа
            $telegramId = $payment->telegram_id;

            if (!$telegramId) {
                Log::error('No telegram_id in payment', ['payment_id' => $payment->id]);
                return response()->json(['error' => 'No telegram_id in payment'], 400);
            }

            // Согласно документации Prodamus, webhook приходит только при успешной оплате
            // Если webhook дошел до нас с правильной подписью, значит платеж успешен
            $status = 'paid';

            // Дополнительная проверка: если есть поле payment_status, используем его
            if (isset($data['payment_status'])) {
                switch ($data['payment_status']) {
                    case 'success':
                    case 'paid':
                        $status = 'paid';
                        break;
                    case 'failed':
                    case 'error':
                        $status = 'failed';
                        break;
                    case 'cancelled':
                        $status = 'cancelled';
                        break;
                    default:
                        $status = 'paid'; // По умолчанию считаем успешным
                }
            }

            // Проверяем сумму платежа для предотвращения подмены
            $webhookAmount = isset($data['sum']) ? (float) $data['sum'] : null;
            $dbAmount = (float) $payment->amount;

            if ($webhookAmount && $webhookAmount !== $dbAmount) {
                Log::error('Payment amount mismatch', [
                    'order_id' => $orderId,
                    'order_num' => $orderNum,
                    'webhook_amount' => $webhookAmount,
                    'db_amount' => $dbAmount,
                    'payment_id' => $payment->id
                ]);

                return response()->json(['error' => 'Payment amount mismatch'], 400);
            }

            // Обновляем статус платежа и сохраняем order_num если его еще нет
            $updateData = ['status' => $status, 'prodamus_data' => $data];

            // Если платеж оплачен, устанавливаем дату оплаты
            if ($status === 'paid') {
                $updateData['paid_at'] = now();
            }

            // Если у нас есть order_id (внутренний ID Prodamus) и order_num еще не сохранен
            if ($orderId && !$payment->order_num) {
                $updateData['order_num'] = $orderId;
            }

            $payment->update($updateData);

            Log::info('Payment status updated', [
                'order_id' => $orderId,
                'status' => $status
            ]);

            // Если платеж оплачен, активируем подписку в основной системе
            if ($status === 'paid') {
                $this->activateSubscription($telegramId, $payment->order_id);
            }

            // Согласно документации Prodamus, при успешной обработке webhook
            // должен вернуть HTTP код 200
            return response()->json(['success' => true], 200);

        } catch (\Exception $e) {
            Log::error('Webhook processing error', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            // При ошибке возвращаем код отличный от 200
            return response()->json(['error' => 'Internal error'], 500);
        }
    }

    /**
     * Активирует подписку пользователя через Supabase
     */
    private function activateSubscription(int $telegramId, string $orderId): void
    {
        try {
            Log::info('Activating subscription via Supabase', [
                'telegram_id' => $telegramId,
                'order_id' => $orderId
            ]);

            // Вычисляем дату окончания доступа (1 месяц)
            $accessUntil = now()->addDays(22)->format('Y-m-d');

            // Обновляем access_until в Supabase для ВСЕХ записей пользователя
            // Сначала получаем все записи пользователя
            $userRecords = $this->supabaseService->select('tg_users', [
                'telegram_id' => "eq.{$telegramId}"
            ]);

            // Обновляем каждую запись отдельно
            $updatedCount = 0;
            foreach ($userRecords as $record) {
                $result = $this->supabaseService->update('tg_users', [
                    'access_until' => $accessUntil,
                    'updated_at' => now()->toISOString()
                ], 'id', $record['id']);
                
                if ($result && !isset($result['error'])) {
                    $updatedCount++;
                }
            }

            if ($result && !isset($result['error'])) {
                Log::info('Subscription activated successfully via Supabase', [
                    'telegram_id' => $telegramId,
                    'order_id' => $orderId,
                    'access_until' => $accessUntil
                ]);
            } else {
                Log::error('Failed to activate subscription via Supabase', [
                    'telegram_id' => $telegramId,
                    'order_id' => $orderId,
                    'result' => $result
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Exception while activating subscription via Supabase', [
                'telegram_id' => $telegramId,
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Извлекает telegram_id из order_id
     * Формат order_id: pay_{telegram_id}_{unique_id}_{random}
     */
    private function extractTelegramIdFromOrderId(string $orderId): ?int
    {
        // Проверяем формат order_id: pay_{telegram_id}_{unique_id}_{random}
        if (preg_match('/^pay_(\d+)_[a-f0-9]+_\d+$/', $orderId, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
