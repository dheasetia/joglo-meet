<?php

namespace App\Filament\Resources\WhatsappMessages\Pages;

use App\Filament\Resources\WhatsappMessages\WhatsappMessageResource;
use App\Services\WhatsApp\Phone;
use App\Services\WhatsApp\WhatsAppNotifier;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ManageWhatsappMessages extends ManageRecords
{
    protected static string $resource = WhatsappMessageResource::class;

    /** Peringatan jelas bila masih mode uji coba (pesan tidak benar-benar terkirim). */
    public function getSubheading(): string|Htmlable|null
    {
        return match (true) {
            config('whatsapp.driver') !== 'fonnte' => new HtmlString('<span style="color:#b45309;font-weight:600">Mode uji coba (WHATSAPP_DRIVER=log): pesan hanya dicatat, tidak dikirim.</span> Isi FONNTE_TOKEN dan ubah WHATSAPP_DRIVER=fonnte di .env untuk mengirim sungguhan.'),
            blank(config('whatsapp.fonnte.token')) => new HtmlString('<span style="color:#be123c;font-weight:600">FONNTE_TOKEN belum diisi di .env</span> — semua pesan akan gagal.'),
            default => 'Pesan dikirim lewat Fonnte, satu per satu dengan jeda beberapa detik.',
        };
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('kirimUji')
                ->label('Kirim pesan uji')
                ->icon('heroicon-o-paper-airplane')
                ->color('gray')
                ->modalHeading('Kirim pesan uji WhatsApp')
                ->modalSubmitActionLabel('Kirim')
                ->schema([
                    TextInput::make('phone')->label('Nomor WhatsApp')->placeholder('08xxxxxxxxxx')->required()
                        ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                            if (! Phone::normalize($value)) {
                                $fail('Nomor WhatsApp tidak valid. Contoh: 081234567890.');
                            }
                        }),
                    Textarea::make('message')->label('Pesan')->required()->rows(4)
                        ->default("Assalamu'alaikum. Ini pesan uji dari aplikasi Manajemen Rapat ".config('rapat.institution').'.'),
                ])
                ->action(function (array $data) {
                    app(WhatsAppNotifier::class)->queue(null, 'uji', $data['message'], phone: $data['phone']);
                    Notification::make()->title('Pesan uji diantrekan')->body('Akan terkirim dalam satu–dua menit (cron schedule:run).')->success()->send();
                }),
        ];
    }
}
