<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('area_id')->constrained()->restrictOnDelete();
            $table->string('title', 150);
            $table->text('description');
            $table->dateTime('occurred_at');
            $table->string('location_description', 500);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('status', 30)->default('baru');
            $table->string('evidence_path')->nullable();
            $table->string('evidence_mime', 50)->nullable();
            $table->unsignedInteger('evidence_size')->nullable();
            $table->text('closure_note')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();

            $table->index(['reporter_id', 'created_at']);
            $table->index(['area_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
