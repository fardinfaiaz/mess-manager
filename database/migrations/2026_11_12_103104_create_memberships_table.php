<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('mess_id')->constrained();
            $table->foreignId('room_id')->nullable()->constrained();
            $table->enum('role', ['manager', 'room_leader', 'resident'])->default('resident');
            $table->enum('status', ['active', 'left'])->default('active');
            $table->date('joined_at');
            $table->date('left_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};