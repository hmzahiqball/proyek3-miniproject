<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_forwardings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('coordinator_id')->constrained('users')->restrictOnDelete();
            $table->string('recipient', 150);
            $table->dateTime('forwarded_at');
            $table->text('note');
            $table->dateTime('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_forwardings');
    }
};
