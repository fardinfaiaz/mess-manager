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
    Schema::create('expenses', function (Blueprint $table) {
        $table->id();
        $table->foreignId('billing_period_id')->constrained()->cascadeOnDelete();
        $table->foreignId('membership_id')->constrained()->cascadeOnDelete();
        $table->string('category'); // BAZAR, ELECTRICITY, GAS, WATER, INTERNET, REPAIR, OTHER
        $table->decimal('amount', 10, 2);
        $table->date('date');
        $table->text('note')->nullable();
        $table->string('receipt_url')->nullable();
        $table->boolean('paid_out_of_pocket')->default(false);
        $table->string('status')->default('pending'); // pending, approved, rejected
        $table->string('rejection_reason')->nullable();
        $table->timestamps();
    });
}
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
