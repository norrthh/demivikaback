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
        // Временно удаляем уникальный индекс
        Schema::table('user_recipes', function (Blueprint $table) {
            try {
                $table->dropUnique('user_recipes_telegram_id_recipe_id_week_start_unique');
            } catch (Exception $e) {
                echo "Индекс уже удален или не существует\n";
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Восстанавливаем уникальный индекс
        Schema::table('user_recipes', function (Blueprint $table) {
            $table->unique(['telegram_id', 'recipe_id', 'week_start'], 'user_recipes_telegram_id_recipe_id_week_start_unique');
        });
    }
};
