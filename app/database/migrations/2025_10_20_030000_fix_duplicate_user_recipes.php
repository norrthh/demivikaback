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
        // Для SQLite используем другой подход
        if (DB::getDriverName() === 'sqlite') {
            // Удаляем дублирующиеся записи, оставляя только самые новые (SQLite синтаксис)
            DB::statement("
                DELETE FROM user_recipes 
                WHERE id NOT IN (
                    SELECT MAX(id) 
                    FROM user_recipes 
                    GROUP BY telegram_id, week, date
                )
            ");
        } else {
            // Удаляем дублирующиеся записи, оставляя только самые новые (MySQL синтаксис)
            DB::statement("
                DELETE ur1 FROM user_recipes ur1
                INNER JOIN user_recipes ur2
                WHERE ur1.id < ur2.id
                AND ur1.telegram_id = ur2.telegram_id
                AND ur1.week = ur2.week
                AND ur1.date = ur2.date
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Нельзя откатить удаление дублирующихся записей
    }
};
