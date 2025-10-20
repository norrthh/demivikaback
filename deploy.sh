#!/bin/bash

# Скрипт автоматического деплоя с SSL
# Использование: ./deploy.sh your-domain.com

set -e  # Останавливаем выполнение при ошибке

# Проверяем, что домен передан
if [ -z "$1" ]; then
    echo "Использование: $0 <domain>"
    echo "Пример: $0 api.labsbery.ru"
    exit 1
fi

DOMAIN=$1
echo "🚀 Начинаем деплой для домена: $DOMAIN"

# Обновляем конфигурацию nginx с новым доменом
echo "📝 Обновляем конфигурацию nginx..."
sed -i "s/api\.labsbery\.ru/$DOMAIN/g" docker/nginx/prod.conf

# Останавливаем текущие контейнеры
echo "🛑 Останавливаем текущие контейнеры..."
docker-compose down 2>/dev/null || true
docker-compose -f docker-compose.prod.yml down 2>/dev/null || true

# Проверяем, что домен доступен
echo "🌐 Проверяем доступность домена..."
if ! nslookup $DOMAIN > /dev/null 2>&1; then
    echo "❌ Домен $DOMAIN не резолвится. Проверьте DNS настройки."
    exit 1
fi

# Проверяем firewall
echo "🔥 Настраиваем firewall..."
if command -v ufw &> /dev/null; then
    sudo ufw allow 80 2>/dev/null || true
    echo "✅ Firewall настроен"
fi

# Собираем и запускаем контейнеры
echo "🐳 Собираем и запускаем Docker контейнеры..."
docker-compose -f docker-compose.prod.yml build --no-cache
docker-compose -f docker-compose.prod.yml up -d

# Ждем запуска контейнеров
echo "⏳ Ждем запуска сервисов..."
sleep 10

# Проверяем статус контейнеров
echo "📊 Проверяем статус контейнеров..."
docker-compose -f docker-compose.prod.yml ps

# Проверяем доступность HTTP
echo "🌐 Проверяем доступность HTTP..."
if curl -s -o /dev/null -w "%{http_code}" http://$DOMAIN | grep -q "200"; then
    echo "✅ HTTP доступен"
else
    echo "⚠️ HTTP недоступен"
fi

echo ""
echo "🎉 Деплой завершен!"
echo "🌐 Ваш сайт доступен по адресу: http://$DOMAIN"
echo "📊 Проверьте статус: docker-compose -f docker-compose.prod.yml ps"
echo "📝 Логи: docker-compose -f docker-compose.prod.yml logs -f"
