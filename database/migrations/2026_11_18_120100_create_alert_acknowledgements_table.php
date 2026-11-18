<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_acknowledgements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emergency_alert_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->constrained();
            $table->dateTime('acknowledged_at');
            $table->timestamps();

            // A member can acknowledge a given alert only once
            $table->unique(['emergency_alert_id', 'membership_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_acknowledgements');
    }
};
