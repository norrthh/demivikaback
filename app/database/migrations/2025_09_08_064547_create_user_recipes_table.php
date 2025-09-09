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
        Schema::create('user_recipes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('telegram_id')->comment('ID пользователя');
            $table->uuid('recipe_id')->comment('ID рецепта из Supabase');
            $table->date('week_start')->comment('Дата начала недели');
            $table->timestamps();

            $table->unique(['telegram_id', 'recipe_id', 'week_start']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_recipes');
    }
};
