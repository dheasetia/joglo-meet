<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Buat (atau setel ulang) akun Super Admin — untuk server produksi tanpa data contoh.
 * Contoh: php artisan rapat:buat-admin admin@jogloquran.com --nama="Administrator"
 */
class CreateAdmin extends Command
{
    protected $signature = 'rapat:buat-admin
        {email : Email untuk masuk}
        {--nama=Administrator : Nama yang ditampilkan}
        {--password= : Kata sandi (kosongkan untuk dibuatkan acak)}';

    protected $description = 'Buat akun Super Admin (atau setel ulang kata sandinya bila email sudah ada)';

    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Email tidak valid.');

            return self::FAILURE;
        }

        $password = $this->option('password') ?: Str::password(14, symbols: false);
        if (strlen($password) < 8) {
            $this->error('Kata sandi minimal 8 karakter.');

            return self::FAILURE;
        }

        $user = User::firstOrNew(['email' => $email]);
        $isNew = ! $user->exists;
        $user->fill([
            'name' => $isNew ? $this->option('nama') : $user->name,
            'password' => $password,
            'role' => Role::SuperAdmin,
            'is_active' => true,
        ])->save();

        $this->info(($isNew ? 'Akun Super Admin dibuat' : 'Akun diperbarui menjadi Super Admin & kata sandi disetel ulang').": {$email}");
        if (! $this->option('password')) {
            $this->line("Kata sandi: {$password}");
            $this->warn('Simpan kata sandi ini, lalu ganti setelah masuk (/admin → Pengguna).');
        }

        return self::SUCCESS;
    }
}
