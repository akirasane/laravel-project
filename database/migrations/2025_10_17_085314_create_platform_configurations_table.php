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
        Schema::create('platform_configurations', function (Blueprint $table) {
            $table->id();
            $table->enum('platform_type', ['shopee', 'lazada', 'shopify', 'tiktok'])->unique();
            $table->json('credentials'); // Encrypted credentials
            $table->integer('sync_interval')->default(300); // seconds
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sync')->nullable();
            $table->json('settings')->nullable(); // Additional platform-specific settings
            $table->timestamps();
            
            // Indexes
            $table->index('platform_type');
            $table->index('is_active');
            $table->index('last_sync');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_configurations');
    }
};
