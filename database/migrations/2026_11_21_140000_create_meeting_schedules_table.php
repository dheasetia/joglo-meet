<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Jadwal rapat rutin: rapat dibuat otomatis tiap pagi untuk N hari ke depan
        Schema::create('meeting_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('meeting_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('frequency', 20);                         // mingguan | dua_mingguan | bulanan
            $table->unsignedTinyInteger('day_of_week');              // 1 = Senin … 7 = Ahad (ISO)
            $table->unsignedTinyInteger('week_of_month')->nullable(); // bulanan: 1–4, 5 = pekan terakhir
            $table->time('start_time');
            $table->time('end_time')->nullable();
            $table->string('location')->nullable();
            $table->string('online_link')->nullable();
            $table->foreignId('chair_id')->constrained('users');
            $table->foreignId('secretary_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('confidentiality', 20)->default('biasa');
            $table->json('participant_ids')->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->unsignedTinyInteger('lead_days')->default(7);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_schedules');
    }
};
