<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mess_id')->constrained();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['mess_id', 'name']);
            $table->index(['mess_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_types');
    }
};
