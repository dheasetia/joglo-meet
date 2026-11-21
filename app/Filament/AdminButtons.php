<?php

namespace App\Filament;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\Size;
use Filament\Support\Facades\FilamentIcon;
use Filament\View\PanelsIconAlias;

/**
 * Ikon & varian tombol panel /admin, diseragamkan dengan <x-btn> di aplikasi rapat.
 * Warna, hover, dan animasinya diatur di resources/views/filament/button-styles.blade.php.
 *
 * Pemetaan warna Filament → varian: primary = hijau, gray = abu tua (secondary),
 * danger = merah, warning = kuning.
 */
class AdminButtons
{
    // Catatan: Filament menyuntikkan argumen closure berdasarkan NAMA parameter.
    // Pada modalSubmitAction()/modalCancelAction()/createAnotherAction() parameternya
    // harus bernama $action; nama lain membuat Filament menyerahkan aksi induknya.

    public static function register(): void
    {
        // Tambah/Baru → plus. Modal: Simpan → check, Buat & buat lainnya → plus, Batal → arrow-uturn-left (warning)
        CreateAction::configureUsing(fn (CreateAction $action) => $action
            ->icon('heroicon-o-plus')
            ->color('primary')
            ->modalSubmitAction(fn (Action $action) => $action->icon('heroicon-o-check'))
            ->createAnotherAction(fn (Action $action) => $action->icon('heroicon-o-plus')->color('primary'))
            ->modalCancelAction(fn (Action $action) => $action->icon('heroicon-o-arrow-uturn-left')->color('warning')));

        // Ubah → pencil-square (secondary). Di tabel tampil sebagai tombol, bukan tautan teks.
        EditAction::configureUsing(fn (EditAction $action) => $action
            ->button()
            ->size(Size::Small)
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->modalSubmitAction(fn (Action $action) => $action->icon('heroicon-o-check')->color('primary'))
            ->modalCancelAction(fn (Action $action) => $action->icon('heroicon-o-arrow-uturn-left')->color('warning')));

        // Hapus → trash (danger). Modal konfirmasi: Hapus → trash, Batal → arrow-left (secondary),
        // sama seperti modal konfirmasi di aplikasi rapat.
        DeleteAction::configureUsing(fn (DeleteAction $action) => $action
            ->button()
            ->size(Size::Small)
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->modalSubmitAction(fn (Action $action) => $action->icon('heroicon-o-trash'))
            ->modalCancelAction(fn (Action $action) => $action->icon('heroicon-o-arrow-left')->color('gray')));

        // Tombol "Masuk" di halaman login /admin
        Action::configureUsing(function (Action $action): void {
            if ($action->getName() === 'authenticate') {
                $action->icon('heroicon-o-arrow-right-end-on-rectangle');
            }
        });

        // Keluar → arrow-right-on-rectangle (widget akun & menu pengguna)
        FilamentIcon::register([
            PanelsIconAlias::WIDGETS_ACCOUNT_LOGOUT_BUTTON => 'heroicon-o-arrow-right-on-rectangle',
            PanelsIconAlias::USER_MENU_LOGOUT_BUTTON => 'heroicon-o-arrow-right-on-rectangle',
        ]);
    }
}
