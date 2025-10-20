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
        // Колонки уже добавлены в предыдущих миграциях, ничего не делаем
        // Эта миграция больше не нужна
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
