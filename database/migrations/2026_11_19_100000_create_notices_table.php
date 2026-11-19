<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mess_id')->constrained();
            $table->foreignId('room_id')->nullable()->constrained();
            $table->foreignId('created_by')->constrained('users');
            $table->string('title', 150);
            $table->text('body');
            $table->enum('category', ['meeting', 'payment', 'rule', 'maintenance', 'general'])->default('general');
            $table->enum('scope', ['mess', 'room'])->default('mess');
            $table->boolean('pinned')->default(false);
            $table->dateTime('expires_at')->nullable();
            $table->boolean('is_automatic')->default(false);
            $table->timestamps();

            $table->index(['mess_id', 'pinned', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notices');
    }
};
