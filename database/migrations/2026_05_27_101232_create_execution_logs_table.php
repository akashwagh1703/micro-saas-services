<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('execution_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_execution_id')->constrained()->cascadeOnDelete();
            $table->string('node_id');
            $table->string('node_type');
            $table->enum('status', ['pending', 'running', 'completed', 'failed', 'skipped'])->default('pending');
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['workflow_execution_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('execution_logs');
    }
};
