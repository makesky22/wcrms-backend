<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payments')) return;
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ward_id')->constrained('wards')->cascadeOnDelete();
            $table->string('billing_month', 7);
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['unpaid', 'paid'])->default('unpaid');
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_ref')->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['resident_id', 'billing_month']);
            $table->index(['ward_id', 'billing_month']);
            $table->index(['resident_id', 'status']);
        });
    }

    public function down(): void { Schema::dropIfExists('payments'); }
};
