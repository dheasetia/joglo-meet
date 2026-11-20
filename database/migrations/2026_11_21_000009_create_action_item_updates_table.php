<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('action_item_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('action_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Diisi bila progres dicatat saat agenda "Evaluasi RTL" pada rapat berikutnya
            $table->foreignId('meeting_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('progress');
            $table->string('status', 20);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_item_updates');
    }
};
