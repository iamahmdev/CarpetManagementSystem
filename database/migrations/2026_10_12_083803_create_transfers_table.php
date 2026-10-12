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
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique()->nullable();
            $table->foreignId('from_location_id')->constrained('locations');
            $table->foreignId('to_location_id')->constrained('locations');
            $table->string('status')->default('in_transit');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('sent_at');
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });

        Schema::create('transfer_pieces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained('transfers')->cascadeOnDelete();
            $table->foreignId('piece_id')->constrained('pieces')->cascadeOnDelete();
            $table->unique(['transfer_id', 'piece_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfer_pieces');
        Schema::dropIfExists('transfers');
    }
};
