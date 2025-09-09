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
        Schema::create('user_registrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('telegram_id')->comment('ID пользователя');
            $table->string('height')->nullable(); // рост в см
            $table->string('weight')->nullable(); // вес в кг

            $table->string('goal')->nullable();       // цель ("Сбросить вес", "Набрать массу" и т.д.)
            $table->string('fitness')->nullable();    // уровень физ. подготовки
            $table->string('diet')->nullable();       // тип диеты
            $table->string('time')->nullable();       // доступное время (например: "3–5 часов")
            $table->string('motivation')->nullable(); // мотивация

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_registrations');
    }
};
