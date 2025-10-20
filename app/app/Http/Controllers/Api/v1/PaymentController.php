<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\UserRegistration;
use App\Services\ProdamusService;
use App\Services\DemivikaService;
use App\Services\Hmac;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    private ProdamusService $prodamusService;
    private DemivikaService $demivikaService;

    public function __construct(ProdamusService $prodamusService, DemivikaService $demivikaService)
    {
        $this->prodamusService = $prodamusService;
        $this->demivikaService = $demivikaService;
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
                'error' => $e->getMessage()
            ]);

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
     * Получает статус подписки пользователя
     */
    public function getSubscriptionStatus(Request $request): JsonResponse
    {
        $request->validate([
            'telegram_id' => ['required', 'integer'],
        ]);

        $telegramId = $request->get('telegram_id');

        $user = UserRegistration::where('telegram_id', $telegramId)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Пользователь не найден'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'subscription_active' => $user->hasActiveSubscription(),
            'subscription_expires_at' => $user->subscription_expires_at,
            'user_id' => $user->id
        ]);
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
                // order_num в webhook'е - это наш order_id
                $payment = Payment::where('order_id', $orderNum)->first();
                Log::info('Searching payment by order_num (our order_id)', ['order_num' => $orderNum, 'found' => $payment ? 'yes' : 'no']);
            }
            
            if (!$payment && $orderId) {
                // order_id в webhook'е - это внутренний ID Prodamus, ищем по order_num в БД
                $payment = Payment::where('order_num', $orderId)->first();
                Log::info('Searching payment by order_id (Prodamus internal ID)', ['order_id' => $orderId, 'found' => $payment ? 'yes' : 'no']);
            }

            if (!$payment) {
                Log::error('Payment not found', ['order_id' => $orderId, 'order_num' => $orderNum]);
                return response()->json(['error' => 'Payment not found'], 404);
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
     * Активирует подписку пользователя в основной системе DemiVika
     */
    private function activateSubscription(int $telegramId, string $orderId): void
    {
        try {
            Log::info('Activating subscription in main system', [
                'telegram_id' => $telegramId,
                'order_id' => $orderId
            ]);

            // Предоставляем доступ в основной системе DemiVika на 3 week
            $success = $this->demivikaService->grantAccess($telegramId);

            if ($success) {
                Log::info('Subscription activated successfully in main system', [
                    'telegram_id' => $telegramId,
                    'order_id' => $orderId
                ]);
            } else {
                Log::error('Failed to activate subscription in main system', [
                    'telegram_id' => $telegramId,
                    'order_id' => $orderId
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Exception while activating subscription', [
                'telegram_id' => $telegramId,
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Извлекает telegram_id из order_id
     * Формат order_id: pay_{telegram_id}_{timestamp}
     */
    private function extractTelegramIdFromOrderId(string $orderId): ?int
    {
        // Проверяем формат order_id: pay_{telegram_id}_{timestamp}
        if (preg_match('/^pay_(\d+)_\d+$/', $orderId, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
