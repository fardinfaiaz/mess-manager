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
    Schema::create('billing_periods', function (Blueprint $table) {
        $table->id();
        $table->foreignId('mess_id')->constrained()->cascadeOnDelete();
        $table->unsignedTinyInteger('month');
        $table->unsignedSmallInteger('year');
        $table->string('status')->default('open'); // open or closed
        $table->timestamp('closed_at')->nullable();
        $table->timestamps();

        $table->unique(['mess_id', 'month', 'year']);
    });
}
    public function down(): void
    {
        Schema::dropIfExists('billing_periods');
    }
};
