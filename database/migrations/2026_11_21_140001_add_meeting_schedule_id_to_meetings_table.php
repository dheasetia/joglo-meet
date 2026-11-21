<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            // Rapat yang dibuat dari jadwal rutin; satu jadwal hanya satu rapat per tanggal
            $table->foreignId('meeting_schedule_id')->nullable()->after('unit_id')->constrained()->nullOnDelete();
            $table->unique(['meeting_schedule_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            // Foreign key dilepas dulu: di MySQL index unik ini ikut dipakai oleh foreign key
            $table->dropForeign(['meeting_schedule_id']);
            $table->dropUnique(['meeting_schedule_id', 'date']);
            $table->dropColumn('meeting_schedule_id');
        });
    }
};
