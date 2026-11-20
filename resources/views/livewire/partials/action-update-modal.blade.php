{{-- Modal perbarui progres RTL. Variabel: $updating (ActionItem|null) --}}
@if ($updating)
    <div class="fixed inset-0 z-40 flex items-end justify-center bg-slate-900/40 p-0 sm:items-center sm:p-4" wire:keydown.escape.window="closeUpdate">
        <div class="absolute inset-0" wire:click="closeUpdate"></div>
        <div class="relative max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-t-2xl bg-white p-5 shadow-xl sm:rounded-2xl">
            <div class="mb-4">
                <p class="text-xs text-slate-500">{{ $updating->meeting->title }} · deadline {{ $updating->due_date->translatedFormat('d M Y') }}</p>
                <h3 class="text-lg font-semibold text-slate-900">{{ $updating->title }}</h3>
                @if ($updating->success_indicator) <p class="text-xs text-brand-700">Indikator selesai: {{ $updating->success_indicator }}</p> @endif
            </div>

            <div class="space-y-4" x-data="{ p: $wire.entangle('progressForm.progress') }">
                <div>
                    <label class="label">Status</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach (\App\Enums\ActionStatus::cases() as $s)
                            <label class="cursor-pointer">
                                <input type="radio" wire:model="progressForm.status" value="{{ $s->value }}" class="peer sr-only"
                                       @if ($s === \App\Enums\ActionStatus::Selesai) x-on:change="p = 100" @endif>
                                <span class="block rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 peer-checked:border-brand-500 peer-checked:bg-brand-50 peer-checked:text-brand-700">{{ $s->label() }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <label class="label">Progres: <span x-text="p + '%'"></span></label>
                    <input type="range" min="0" max="100" step="5" x-model.number="p" class="w-full accent-brand-600">
                    @error('progressForm.progress') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Catatan / bukti</label>
                    <textarea wire:model="progressForm.note" rows="3" class="input" placeholder="Apa yang sudah dikerjakan, kendala, atau tautan bukti…"></textarea>
                </div>
            </div>

            @if ($updating->updates->isNotEmpty())
                <div class="mt-5 border-t border-slate-100 pt-4">
                    <p class="mb-2 text-xs font-bold tracking-wide text-slate-400 uppercase">Riwayat</p>
                    <ul class="max-h-40 space-y-2 overflow-y-auto">
                        @foreach ($updating->updates->take(10) as $u)
                            <li class="text-xs text-slate-600">
                                <span class="font-semibold">{{ $u->created_at->translatedFormat('d M H:i') }}</span>
                                · {{ $u->user?->name }} · {{ $u->status->label() }} {{ $u->progress }}%
                                @if ($u->note) <br><span class="text-slate-500">{{ $u->note }}</span> @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mt-5 flex justify-end gap-2">
                <x-btn variant="warning" icon="arrow-uturn-left" wire:click="closeUpdate">Batal</x-btn>
                <x-btn icon="check" wire:click="saveUpdate">Simpan Progres</x-btn>
            </div>
        </div>
    </div>
@endif
