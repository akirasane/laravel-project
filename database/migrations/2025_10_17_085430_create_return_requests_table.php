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
        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->string('return_authorization_number')->unique();
            $table->enum('return_type', ['in_store', 'mail']);
            $table->enum('reason_code', ['defective', 'wrong_item', 'not_as_described', 'changed_mind', 'damaged_shipping', 'other']);
            $table->text('reason_description')->nullable();
            $table->enum('status', ['requested', 'approved', 'rejected', 'in_transit', 'received', 'processed', 'completed']);
            $table->decimal('return_amount', 10, 2);
            $table->json('items_to_return'); // Array of order item IDs and quantities
            $table->string('shipping_label_url')->nullable();
            $table->string('tracking_number')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('processing_notes')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('order_id');
            $table->index('return_authorization_number');
            $table->index('return_type');
            $table->index('status');
            $table->index('requested_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('return_requests');
    }
};
