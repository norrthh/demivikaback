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
        // Удаляем дублирующиеся записи, оставляя только самые новые
        DB::statement("
            DELETE ur1 FROM user_recipes ur1
            INNER JOIN user_recipes ur2 
            WHERE ur1.id < ur2.id 
            AND ur1.telegram_id = ur2.telegram_id 
            AND ur1.recipe_id = ur2.recipe_id 
            AND ur1.week_start = ur2.week_start
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Нельзя откатить удаление дублирующихся записей
    }
};
