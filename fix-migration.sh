#!/bin/bash

# Скрипт для исправления проблемы с миграциями

echo "🔧 Исправляем проблему с миграциями..."

# Переходим в директорию приложения
cd app

echo "📊 Проверяем текущее состояние миграций..."
php artisan migrate:status

echo ""
echo "🗑️ Удаляем дублирующиеся записи из user_recipes..."
php artisan migrate --path=database/migrations/2025_10_20_030000_fix_duplicate_user_recipes.php

echo ""
echo "🔄 Продолжаем выполнение миграций..."
php artisan migrate

echo ""
echo "✅ Миграции завершены!"
echo "📊 Проверяем финальное состояние..."
php artisan migrate:status
