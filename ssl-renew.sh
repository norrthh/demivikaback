#!/bin/bash

# Скрипт для автоматического обновления SSL сертификатов Let's Encrypt

echo "🔄 Проверяем обновление SSL сертификатов..."

# Переходим в директорию проекта
cd "$(dirname "$0")"

# Обновляем сертификат
certbot renew --quiet --no-self-upgrade

# Проверяем, обновился ли сертификат
if [ $? -eq 0 ]; then
    echo "✅ Сертификат проверен/обновлен"
    
    # Перезапускаем nginx для применения нового сертификата
    echo "🔄 Перезапускаем nginx..."
    docker-compose -f docker-compose.prod.yml restart nginx
    
    if [ $? -eq 0 ]; then
        echo "✅ Nginx перезапущен с новым сертификатом"
    else
        echo "❌ Ошибка при перезапуске nginx"
    fi
else
    echo "ℹ️ Сертификат не требует обновления"
fi

echo "🏁 Процесс завершен"
