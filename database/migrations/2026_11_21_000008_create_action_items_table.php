<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('action_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('decision_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('success_indicator')->nullable();
            $table->foreignId('pic_id')->constrained('users');
            $table->date('due_date');
            $table->string('priority', 10)->default('sedang');
            $table->string('status', 20)->default('belum');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'due_date']);
            $table->index('pic_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_items');
    }
};
