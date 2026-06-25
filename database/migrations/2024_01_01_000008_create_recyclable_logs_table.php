<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('recyclable_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('officer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ward_id')->constrained('wards')->cascadeOnDelete();
            $table->foreignId('schedule_id')->constrained('schedules')->cascadeOnDelete();
            $table->enum('category', ['plastic','metal','glass','paper','organic','other']);
            $table->decimal('quantity_kg', 8, 2);
            $table->timestamp('logged_at');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('recyclable_logs'); }
};
