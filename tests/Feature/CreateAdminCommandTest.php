<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    // Sama dengan tes lain: database in-memory dipakai bersama, jadi semua tes memakai seeder
    protected $seed = true;

    public function test_creates_super_admin_and_resets_existing(): void
    {
        $before = User::count();

        $this->artisan('rapat:buat-admin', ['email' => 'Sekretariat@JogloQuran.com', '--nama' => 'Admin Pondok', '--password' => 'rahasia-123'])
            ->expectsOutputToContain('Akun Super Admin dibuat')
            ->assertSuccessful();

        $admin = User::where('email', 'sekretariat@jogloquran.com')->sole();
        $this->assertSame(Role::SuperAdmin, $admin->role);
        $this->assertSame('Admin Pondok', $admin->name);
        $this->assertTrue(Hash::check('rahasia-123', $admin->password));
        $this->post('/login', ['email' => 'sekretariat@jogloquran.com', 'password' => 'rahasia-123'])->assertRedirect('/');

        // Dijalankan lagi tanpa --password: kata sandi acak dibuat & ditampilkan, tanpa akun ganda
        $this->artisan('rapat:buat-admin', ['email' => 'sekretariat@jogloquran.com'])
            ->expectsOutputToContain('Kata sandi:')
            ->assertSuccessful();
        $this->assertFalse(Hash::check('rahasia-123', $admin->fresh()->password));
        $this->assertSame($before + 1, User::count());

        $this->artisan('rapat:buat-admin', ['email' => 'bukan-email'])->assertFailed();
    }
}
