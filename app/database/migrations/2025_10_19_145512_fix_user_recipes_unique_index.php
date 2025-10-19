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
        Schema::table('user_recipes', function (Blueprint $table) {
            // Удаляем старый уникальный индекс
            $table->dropUnique('user_recipes_telegram_id_recipe_id_week_start_unique');
            
            // Создаем новый уникальный индекс для комбинации telegram_id, week, date
            $table->unique(['telegram_id', 'week', 'date'], 'user_recipes_unique_constraint');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_recipes', function (Blueprint $table) {
            // Удаляем новый уникальный индекс
            $table->dropUnique('user_recipes_unique_constraint');
            
            // Восстанавливаем старый уникальный индекс
            $table->unique(['telegram_id'], 'user_recipes_telegram_id_recipe_id_week_start_unique');
        });
    }
};
