<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pieces', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->unique();
            $table->foreignId('carpet_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('container_id')->nullable();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('in_stock');
            $table->string('grade', 1);
            $table->unsignedBigInteger('landed_cost');
            $table->unsignedBigInteger('price')->nullable();
            $table->timestamp('received_at');
            $table->timestamps();
            $table->index(['location_id', 'status']);
        });

        Schema::create('piece_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('piece_id')->constrained()->restrictOnDelete();
            $table->string('type');
            $table->foreignId('from_location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->string('reference')->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('moved_at');
            $table->index(['piece_id', 'moved_at']);
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->foreignId('piece_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('piece_id');
        });
        Schema::dropIfExists('piece_movements');
        Schema::dropIfExists('pieces');
    }
};
