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
        Schema::create('billing_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->string('invoice_number')->unique();
            $table->enum('billing_method', ['pos_api', 'manual']);
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->enum('payment_status', ['pending', 'paid', 'partially_paid', 'refunded', 'cancelled']);
            $table->enum('payment_method', ['cash', 'card', 'bank_transfer', 'digital_wallet', 'other'])->nullable();
            $table->string('pos_transaction_id')->nullable();
            $table->json('pos_response_data')->nullable();
            $table->timestamp('billed_at');
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('order_id');
            $table->index('invoice_number');
            $table->index('billing_method');
            $table->index('payment_status');
            $table->index('billed_at');
            $table->index('pos_transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_records');
    }
};
