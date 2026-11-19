<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('slot'); // breakfast, lunch, dinner
            $table->boolean('is_on')->default(true);
            $table->timestamps();

            $table->unique(['membership_id', 'date', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_entries');
    }
};