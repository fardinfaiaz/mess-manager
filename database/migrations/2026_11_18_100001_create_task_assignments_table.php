<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mess_id')->constrained();
            $table->foreignId('task_type_id')->constrained('task_types');
            $table->foreignId('membership_id')->constrained('memberships');
            $table->foreignId('assigned_by_membership_id')->constrained('memberships');
            $table->date('due_date');
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('verified_by_membership_id')->nullable()->constrained('memberships');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['mess_id', 'status', 'due_date']);
            $table->index(['membership_id', 'status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_assignments');
    }
};
