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
            $table->dropColumn(['recipe_id', 'week_start']);
            $table->integer('week')->comment('Номер недели');
            $table->date('date')->comment('Дата рецепта');
            $table->json('recipe_data')->comment('Данные рецепта');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_recipes', function (Blueprint $table) {
            $table->dropColumn(['week', 'date', 'recipe_data']);
            $table->uuid('recipe_id')->comment('ID рецепта из Supabase');
            $table->date('week_start')->comment('Дата начала недели');
        });
    }
};
