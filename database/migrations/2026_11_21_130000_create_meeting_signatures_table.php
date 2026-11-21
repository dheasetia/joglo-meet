<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tanda tangan digital (QR) pengesahan notulen
        Schema::create('meeting_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20)->unique();          // mis. JQ-7K4M-Q2XP-9ZRT
            $table->char('content_hash', 64);              // SHA-256 isi notulen saat disahkan
            $table->foreignId('signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('signed_at');
            $table->timestamp('revoked_at')->nullable();   // diisi saat pengesahan dibatalkan (buka kunci)
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['meeting_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_signatures');
    }
};
