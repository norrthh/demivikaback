<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Добавляем уникальный индекс на комбинацию telegram_id и order_id
            // Это предотвратит создание дублирующих платежей для одного пользователя
            $table->unique(['telegram_id', 'order_id'], 'payments_telegram_order_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_telegram_order_unique');
        });
    }
};