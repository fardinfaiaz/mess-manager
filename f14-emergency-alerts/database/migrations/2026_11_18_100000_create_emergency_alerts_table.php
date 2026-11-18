<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emergency_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mess_id')->constrained();
            $table->foreignId('created_by')->constrained('users');
            $table->enum('type', ['water', 'electricity', 'gas', 'safety', 'other']);
            $table->text('message');
            $table->enum('status', ['active', 'resolved'])->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emergency_alerts');
    }
};
