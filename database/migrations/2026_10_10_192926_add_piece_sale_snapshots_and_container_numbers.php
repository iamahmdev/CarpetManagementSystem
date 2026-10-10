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
        Schema::table('pieces', function (Blueprint $table) {
            $table->string('container_number')->nullable()->after('container_id');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->string('piece_code')->nullable()->after('piece_id');
            $table->unsignedBigInteger('unit_cost')->nullable()->after('unit_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['piece_code', 'unit_cost']);
        });

        Schema::table('pieces', function (Blueprint $table) {
            $table->dropColumn('container_number');
        });
    }
};
