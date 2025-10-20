<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Просто добавляем недостающие колонки
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        try {
            if (!Schema::hasColumn('user_recipes', 'week')) {
                DB::statement("ALTER TABLE user_recipes ADD COLUMN week INT NULL COMMENT 'Номер недели'");
                echo "Добавлена колонка week\n";
            }
            if (!Schema::hasColumn('user_recipes', 'date')) {
                DB::statement("ALTER TABLE user_recipes ADD COLUMN date DATE NULL COMMENT 'Дата рецепта'");
                echo "Добавлена колонка date\n";
            }
            if (!Schema::hasColumn('user_recipes', 'recipe_data')) {
                DB::statement("ALTER TABLE user_recipes ADD COLUMN recipe_data JSON NULL COMMENT 'Данные рецепта'");
                echo "Добавлена колонка recipe_data\n";
            }
        } catch (Exception $e) {
            echo "Ошибка при добавлении колонок: " . $e->getMessage() . "\n";
        }
        
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        try {
            if (Schema::hasColumn('user_recipes', 'week')) {
                DB::statement("ALTER TABLE user_recipes DROP COLUMN week");
            }
            if (Schema::hasColumn('user_recipes', 'date')) {
                DB::statement("ALTER TABLE user_recipes DROP COLUMN date");
            }
            if (Schema::hasColumn('user_recipes', 'recipe_data')) {
                DB::statement("ALTER TABLE user_recipes DROP COLUMN recipe_data");
            }
        } catch (Exception $e) {
            echo "Ошибка при удалении колонок: " . $e->getMessage() . "\n";
        }
        
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
};
