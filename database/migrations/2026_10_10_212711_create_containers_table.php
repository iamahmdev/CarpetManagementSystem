<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('containers', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->string('supplier');
            $table->foreignId('destination_id')->constrained('locations')->restrictOnDelete();
            $table->string('status')->default('on_the_way');
            $table->date('eta');
            $table->timestamp('arrived_at')->nullable();
            $table->unsignedBigInteger('freight_cost')->default(0);
            $table->unsignedBigInteger('customs_duty_cost')->default(0);
            $table->unsignedBigInteger('clearing_cost')->default(0);
            $table->unsignedBigInteger('transport_cost')->default(0);
            $table->timestamps();
            $table->index(['status', 'eta']);
        });

        Schema::create('container_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('container_id')->constrained()->cascadeOnDelete();
            $table->foreignId('carpet_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('expected_quantity');
            $table->unsignedBigInteger('unit_price');
            $table->unsignedInteger('received_quantity')->default(0);
            $table->timestamps();
            $table->unique(['container_id', 'carpet_id']);
        });

        Schema::table('pieces', function (Blueprint $table): void {
            $table->foreign('container_id')
                ->references('id')
                ->on('containers')
                ->nullOnDelete();
        });

        $highestPieceId = (int) DB::table('pieces')->max('id');
        $lastPieceId = max(3599, $highestPieceId);
        $connection = DB::connection();

        if ($connection->getDriverName() === 'mysql') {
            $connection->statement('ALTER TABLE pieces AUTO_INCREMENT = '.($lastPieceId + 1));
        } elseif ($connection->getDriverName() === 'sqlite') {
            $connection->table('sqlite_sequence')->updateOrInsert(
                ['name' => 'pieces'],
                ['seq' => $lastPieceId],
            );
        } elseif ($connection->getDriverName() === 'pgsql') {
            $connection->selectOne(
                "SELECT setval(pg_get_serial_sequence('pieces', 'id'), ?, true)",
                [$lastPieceId],
            );
        }
    }

    public function down(): void
    {
        Schema::table('pieces', function (Blueprint $table): void {
            $table->dropForeign(['container_id']);
        });

        Schema::dropIfExists('container_items');
        Schema::dropIfExists('containers');
    }
};
