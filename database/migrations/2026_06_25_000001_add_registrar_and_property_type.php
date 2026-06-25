<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // 1. Add 'registrar' to users role enum
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin','supervisor','officer','registrar','resident'])
                  ->default('resident')->change();
            // Property type for residents
            $table->enum('property_type', ['residential','shop','market'])->nullable()->after('ward_id');
        });

        // 2. Add property_type + billing_rate to payments
        Schema::table('payments', function (Blueprint $table) {
            $table->enum('property_type', ['residential','shop','market'])->nullable()->after('ward_id');
            $table->string('registered_by')->nullable()->after('marked_by'); // registrar name
        });
    }

    public function down(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('property_type');
            $table->enum('role', ['admin','supervisor','officer','resident'])->default('resident')->change();
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['property_type','registered_by']);
        });
    }
};
