<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->default('-');
            $table->string('city')->default('-');
            $table->timestamps();
        });

        Schema::create('document_sequences', function (Blueprint $table) {
            $table->string('document_type');
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->primary(['document_type', 'year']);
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('status')->default('completed');
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('total');
            $table->timestamp('sold_at');
            $table->timestamps();
            $table->index(['customer_id', 'status', 'sold_at']);
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('carpet_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku')->nullable();
            $table->string('name');
            $table->unsignedBigInteger('unit_price');
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number');
            $table->string('legacy_key')->nullable()->unique();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('customer_balance_after')->nullable();
            $table->string('method');
            $table->timestamp('received_at');
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->text('note')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();
            $table->index(['receipt_number', 'status']);
            $table->index(['sale_id', 'status']);
            $table->index(['customer_id', 'received_at']);
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->timestamps();
            $table->unique(['payment_id', 'sale_id']);
        });

        Schema::create('payment_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('receipt_number')->nullable();
            $table->string('event');
            $table->timestamp('created_at');
            $table->index(['receipt_number', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_audits');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('document_sequences');
        Schema::dropIfExists('customers');
    }
};
