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
        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_flow_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->integer('step_order');
            $table->enum('step_type', ['manual', 'automatic', 'approval', 'notification']);
            $table->string('assigned_role')->nullable();
            $table->boolean('auto_execute')->default(false);
            $table->json('conditions')->nullable(); // Conditions for step execution
            $table->json('configuration')->nullable(); // Step-specific configuration
            $table->timestamps();
            
            // Indexes
            $table->index(['process_flow_id', 'step_order']);
            $table->index('step_type');
            $table->index('assigned_role');
            $table->index('auto_execute');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_steps');
    }
};
