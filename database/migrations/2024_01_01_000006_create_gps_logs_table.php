<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('gps_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('officer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('schedule_id')->constrained('schedules')->cascadeOnDelete();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->timestamp('transmitted_at');
            $table->timestamps();
            $table->index(['vehicle_id', 'transmitted_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('gps_logs'); }
};
