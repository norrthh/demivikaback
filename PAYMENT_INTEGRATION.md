# Интеграция платежей с основной системой DemiVika

## Описание

Система платежей теперь интегрирована с основной системой DemiVika. После успешной оплаты через Prodamus, доступ автоматически предоставляется пользователю в основной системе.

## Как это работает

1. **Создание платежа**: Пользователь создает платеж через API `/api/v1/payment/create`
2. **Оплата**: Пользователь переходит по ссылке и оплачивает через Prodamus
3. **Webhook**: Prodamus отправляет уведомление на `/api/v1/payment/webhook`
4. **Активация доступа**: Система автоматически предоставляет доступ в основной системе DemiVika через API `grant-access`

## Настройка

### Переменные окружения

Добавьте в `.env` файл:

```env
# Prodamus Payment System Configuration
PRODAMUS_BASE_URL=https://demivika.payform.ru/
PRODAMUS_PAYFORM_URL=https://demivika.payform.ru/
PRODAMUS_SECRET_KEY=your_prodamus_secret_key_here
PRODAMUS_SYS_CODE=tma

# DemiVika Main System Configuration
DEMIVIKA_BASE_URL=https://demivika.ru/
DEMIVIKA_API_KEY=your_demivika_api_key_here
```

### Настройка в основной системе

В основной системе DemiVika должен быть настроен API ключ в переменной окружения `ADMIN_API_KEY`, который используется для авторизации запросов к `/api/admin/grant-access`.

## API Endpoints

### Создание платежа
```
POST /api/v1/payment/create
{
    "telegram_id": 123456789
}
```

### Webhook от Prodamus
```
POST /api/v1/payment/webhook
Headers: Sign: {signature}
Body: {payment_data}
```

## Структура данных

### Order ID
Формат: `pay_{telegram_id}_{timestamp}`
Пример: `pay_123456789_1640995200`

### Webhook данные
Webhook от Prodamus содержит:
- `order_id` - идентификатор заказа
- `payment_status` - статус платежа (опционально)
- Другие поля согласно документации Prodamus

## Логирование

Все операции логируются в Laravel log:
- Создание платежей
- Получение webhook'ов
- Активация доступа
- Ошибки интеграции

## Безопасность

- Проверка подписи HMAC от Prodamus
- Проверка API ключа для вызовов основной системы
- Логирование всех операций
- Валидация данных

## Отладка

Для отладки проверьте:
1. Логи Laravel в `storage/logs/laravel.log`
2. Правильность настроек в `.env`
3. Доступность API основной системы
4. Корректность API ключей
