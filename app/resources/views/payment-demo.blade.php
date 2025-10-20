<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Демо интеграции с Prodamus</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
        }
        .section {
            margin: 30px 0;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .form-group {
            margin: 15px 0;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            background-color: #007bff;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background-color: #0056b3;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 4px;
            border-left: 4px solid #007bff;
        }
        .error {
            border-left-color: #dc3545;
            background-color: #f8d7da;
        }
        .success {
            border-left-color: #28a745;
            background-color: #d4edda;
        }
        .payment-url {
            word-break: break-all;
            background-color: #e9ecef;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Демо интеграции с Prodamus</h1>
        
        <div class="section" style="background-color: #fff3cd; border-color: #ffeaa7;">
            <h2>⚠️ Настройка продакшн данных</h2>
            <p><strong>Внимание:</strong> Для работы с реальными платежами необходимо настроить продакшн данные Prodamus.</p>
            <p>Добавьте в ваш <code>.env</code> файл:</p>
            <pre style="background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto;">
PRODAMUS_BASE_URL=https://your-subdomain.payform.ru/
PRODAMUS_PAYFORM_URL=https://your-subdomain.payform.ru/
PRODAMUS_SECRET_KEY=your_production_secret_key_here
PRODAMUS_SYS_CODE=your_sys_code_here
            </pre>
            <p>Подробная инструкция в файле <code>PRODAMUS_PRODUCTION_SETUP.md</code></p>
        </div>
        
        <div class="section">
            <h2>Создать платеж за курс "КОД ЖЕНЩИНЫ"</h2>
            <p><strong>Сумма:</strong> 2990 руб.</p>
            <p><strong>Описание:</strong> Доступ к клубу на 1 месяц "КОД ЖЕНЩИНЫ"</p>
            <form id="paymentForm">
                <div class="form-group">
                    <label for="telegram_id">Telegram ID:</label>
                    <input type="number" id="telegram_id" value="123456789" required>
                </div>
                <button type="submit">Создать платеж</button>
            </form>
            <div id="paymentResult" class="result" style="display: none;"></div>
        </div>

        <div class="section">
            <h2>История платежей</h2>
            <form id="historyForm">
                <div class="form-group">
                    <label for="history_telegram_id">Telegram ID:</label>
                    <input type="number" id="history_telegram_id" value="123456789" required>
                </div>
                <button type="submit">Получить историю</button>
            </form>
            <div id="historyResult" class="result" style="display: none;"></div>
        </div>
    </div>

    <script>
        // Обработка формы платежа
        document.getElementById('paymentForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = {
                telegram_id: parseInt(document.getElementById('telegram_id').value)
            };

            try {
                const response = await fetch('/api/v1/payment/create', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                });

                const result = await response.json();
                displayResult('paymentResult', result);
            } catch (error) {
                displayResult('paymentResult', { success: false, message: 'Ошибка: ' + error.message });
            }
        });

        // Обработка формы истории
        document.getElementById('historyForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const telegramId = document.getElementById('history_telegram_id').value;

            try {
                const response = await fetch(`/api/v1/payment/history?telegram_id=${telegramId}`);
                const result = await response.json();
                displayResult('historyResult', result);
            } catch (error) {
                displayResult('historyResult', { success: false, message: 'Ошибка: ' + error.message });
            }
        });

        function displayResult(elementId, result) {
            const element = document.getElementById(elementId);
            element.style.display = 'block';
            
            if (result.success) {
                element.className = 'result success';
                
                if (result.payment_url) {
                    element.innerHTML = `
                        <h3>Успешно!</h3>
                        <p><strong>Order ID:</strong> ${result.order_id}</p>
                        ${result.amount ? `<p><strong>Сумма:</strong> ${result.amount} руб.</p>` : ''}
                        <p><strong>Ссылка на оплату:</strong></p>
                        <div class="payment-url">${result.payment_url}</div>
                        <p><a href="${result.payment_url}" target="_blank">Перейти к оплате</a></p>
                    `;
                } else if (result.payments) {
                    let html = '<h3>История платежей:</h3>';
                    if (result.payments.length === 0) {
                        html += '<p>Платежей не найдено</p>';
                    } else {
                        html += '<ul>';
                        result.payments.forEach(payment => {
                            html += `
                                <li>
                                    <strong>${payment.order_id}</strong> - 
                                    ${payment.type === 'subscription' ? 'Подписка' : 'Разовый платеж'} - 
                                    ${payment.status} - 
                                    ${payment.amount ? payment.amount + ' руб.' : ''}
                                    ${payment.created_at ? ' (' + new Date(payment.created_at).toLocaleString('ru-RU') + ')' : ''}
                                </li>
                            `;
                        });
                        html += '</ul>';
                    }
                    element.innerHTML = html;
                }
            } else {
                element.className = 'result error';
                element.innerHTML = `<h3>Ошибка:</h3><p>${result.message}</p>`;
            }
        }
    </script>
</body>
</html>
