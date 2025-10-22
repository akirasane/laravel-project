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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('platform_order_id');
            $table->enum('platform_type', ['shopee', 'lazada', 'shopify', 'tiktok']);
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->decimal('total_amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->enum('status', ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded']);
            $table->enum('workflow_status', ['new', 'in_progress', 'completed', 'on_hold'])->default('new');
            $table->timestamp('order_date');
            $table->enum('sync_status', ['synced', 'pending', 'failed'])->default('pending');
            $table->json('raw_data')->nullable();
            $table->string('shipping_address')->nullable();
            $table->string('billing_address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['platform_type', 'platform_order_id']);
            $table->index('status');
            $table->index('workflow_status');
            $table->index('order_date');
            $table->index('sync_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
