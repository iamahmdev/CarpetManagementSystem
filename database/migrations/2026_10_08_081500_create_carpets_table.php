<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carpets', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 263);
            $table->string('name');
            $table->string('design');
            $table->string('weave');
            $table->string('material');
            $table->string('color');
            $table->string('size');
            $table->unsignedBigInteger('price');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carpets');
    }
};
