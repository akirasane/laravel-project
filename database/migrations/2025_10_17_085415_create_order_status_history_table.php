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
        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->string('previous_status')->nullable();
            $table->string('new_status');
            $table->string('changed_by_type')->default('user'); // user, system, api
            $table->foreignId('changed_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable(); // Additional context data
            $table->boolean('is_reversible')->default(false);
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('order_id');
            $table->index(['new_status', 'created_at']);
            $table->index('changed_by_id');
            $table->index('is_reversible');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_status_history');
    }
};
