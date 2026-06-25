<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ward_id')->constrained('wards')->cascadeOnDelete();
            $table->enum('type', ['missed','delayed','other']);
            $table->text('description');
            $table->string('photo_path')->nullable();
            $table->enum('status', ['open','in_progress','resolved'])->default('open');
            $table->text('resolution_note')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('complaints'); }
};
