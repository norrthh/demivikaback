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

# Проверяем, установлен ли certbot
if ! command -v certbot &> /dev/null; then
    echo "📦 Устанавливаем certbot..."
    if command -v apt &> /dev/null; then
        sudo apt update
        sudo apt install -y certbot
    elif command -v yum &> /dev/null; then
        sudo yum install -y certbot
    else
        echo "❌ Не удалось установить certbot. Установите его вручную."
        exit 1
    fi
fi

# Проверяем, есть ли уже сертификат
if [ ! -d "/etc/letsencrypt/live/$DOMAIN" ]; then
    echo "🔐 Получаем SSL сертификат для $DOMAIN..."
    
    # Проверяем, что домен доступен
    echo "🌐 Проверяем доступность домена..."
    if ! nslookup $DOMAIN > /dev/null 2>&1; then
        echo "❌ Домен $DOMAIN не резолвится. Проверьте DNS настройки."
        exit 1
    fi
    
    # Получаем сертификат
    sudo certbot certonly --standalone -d $DOMAIN --non-interactive --agree-tos --email admin@$DOMAIN
    
    if [ $? -ne 0 ]; then
        echo "❌ Не удалось получить SSL сертификат. Проверьте настройки домена."
        exit 1
    fi
    
    echo "✅ SSL сертификат получен успешно!"
else
    echo "✅ SSL сертификат уже существует для $DOMAIN"
fi

# Проверяем firewall
echo "🔥 Настраиваем firewall..."
if command -v ufw &> /dev/null; then
    sudo ufw allow 80 2>/dev/null || true
    sudo ufw allow 443 2>/dev/null || true
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
if curl -s -o /dev/null -w "%{http_code}" http://$DOMAIN | grep -q "301\|200"; then
    echo "✅ HTTP доступен"
else
    echo "⚠️ HTTP недоступен"
fi

# Проверяем доступность HTTPS
echo "🔒 Проверяем доступность HTTPS..."
if curl -s -o /dev/null -w "%{http_code}" https://$DOMAIN | grep -q "200"; then
    echo "✅ HTTPS доступен"
else
    echo "⚠️ HTTPS недоступен"
fi

# Настраиваем автоматическое обновление SSL
echo "🔄 Настраиваем автоматическое обновление SSL..."
SCRIPT_PATH=$(pwd)/ssl-renew.sh

# Добавляем задачу в crontab если её там нет
if ! crontab -l 2>/dev/null | grep -q "$SCRIPT_PATH"; then
    (crontab -l 2>/dev/null; echo "0 2 1 */2 * $SCRIPT_PATH") | crontab -
    echo "✅ Автоматическое обновление SSL настроено"
fi

echo ""
echo "🎉 Деплой завершен!"
echo "🌐 Ваш сайт доступен по адресу: https://$DOMAIN"
echo "📊 Проверьте статус: docker-compose -f docker-compose.prod.yml ps"
echo "📝 Логи: docker-compose -f docker-compose.prod.yml logs -f"
