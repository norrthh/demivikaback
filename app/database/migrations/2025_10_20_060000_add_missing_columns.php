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
            // Добавляем новые колонки только если их еще нет
            if (!Schema::hasColumn('user_recipes', 'week')) {
                $table->integer('week')->nullable()->comment('Номер недели');
            }
            if (!Schema::hasColumn('user_recipes', 'date')) {
                $table->date('date')->nullable()->comment('Дата рецепта');
            }
            if (!Schema::hasColumn('user_recipes', 'recipe_data')) {
                $table->json('recipe_data')->nullable()->comment('Данные рецепта');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_recipes', function (Blueprint $table) {
            // Удаляем новые колонки
            if (Schema::hasColumn('user_recipes', 'week')) {
                $table->dropColumn('week');
            }
            if (Schema::hasColumn('user_recipes', 'date')) {
                $table->dropColumn('date');
            }
            if (Schema::hasColumn('user_recipes', 'recipe_data')) {
                $table->dropColumn('recipe_data');
            }
        });
    }
};
