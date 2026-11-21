<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kotak keluar & riwayat notifikasi WhatsApp
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone', 20)->nullable();                // dinormalkan: 628…
            $table->string('type', 30);                             // undangan, pengingat_rapat, rtl_baru, …
            $table->foreignId('meeting_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('action_item_id')->nullable()->constrained()->nullOnDelete();
            $table->text('message');
            $table->string('status', 15)->default('antre');         // antre | terkirim | gagal | dilewati
            $table->string('provider_id', 100)->nullable();
            $table->string('error', 500)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->string('dedupe_key', 150)->nullable()->unique(); // cegah pesan ganda
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['meeting_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
