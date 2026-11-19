<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duty_trade_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mess_id')->constrained();
            $table->foreignId('proposer_membership_id')->constrained('memberships');
            $table->foreignId('target_membership_id')->constrained('memberships');
            $table->foreignId('offered_task_assignment_id')->constrained('task_assignments');
            $table->foreignId('requested_task_assignment_id')->constrained('task_assignments');
            $table->text('reason')->nullable();
            $table->string('status', 30)->default('pending_acceptance');
            $table->timestamp('accepted_at')->nullable();
            $table->string('decision_reason')->nullable();
            $table->foreignId('decided_by_membership_id')->nullable()->constrained('memberships');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['mess_id', 'status']);
            $table->index(['proposer_membership_id', 'status']);
            $table->index(['target_membership_id', 'status']);
            $table->index(['offered_task_assignment_id', 'status']);
            $table->index(['requested_task_assignment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duty_trade_requests');
    }
};
