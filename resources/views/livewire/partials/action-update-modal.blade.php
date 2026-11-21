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
                <div x-data="rapatUploads({ property: 'evidenceUploads', append: true })">
                    <div class="mb-1 flex items-center justify-between gap-2">
                        <label class="label mb-0">Bukti <span class="font-normal text-slate-400">(opsional)</span></label>
                        <input type="file" multiple class="sr-only" x-ref="bukti"
                               x-on:change="pick($event.target.files); $event.target.value = ''"
                               accept=".{{ implode(',.', config('rapat.attachments.mimes')) }}">
                        <x-btn variant="secondary" size="sm" icon="paper-clip" x-on:click="$refs.bukti.click()" x-bind:disabled="uploading">Pilih berkas</x-btn>
                    </div>
                    <div x-show="uploading" x-cloak class="mb-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-emerald-600 transition-all" :style="`width: ${progress}%`"></div>
                    </div>
                    @if ($evidenceUploads)
                        <ul class="space-y-1">
                            @foreach ($evidenceUploads as $i => $file)
                                <li wire:key="bukti-{{ $i }}-{{ $file->getFilename() }}" class="flex items-center gap-2 rounded-lg bg-slate-50 px-2 py-1 text-xs text-slate-600">
                                    <x-heroicon-o-paper-clip class="size-3.5 shrink-0 text-slate-400" />
                                    <span class="min-w-0 flex-1 truncate">{{ $file->getClientOriginalName() }}</span>
                                    <span class="shrink-0 text-slate-400">{{ \App\Models\Attachment::formatBytes($file->getSize()) }}</span>
                                    <x-btn variant="danger" size="sm" icon="x-mark" wire:click="removeEvidence({{ $i }})" title="Batalkan berkas ini" aria-label="Batalkan {{ $file->getClientOriginalName() }}" />
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-xs text-slate-400">Foto hasil kerja atau dokumen pendukung. {{ \App\Support\AttachmentStorage::hint() }}</p>
                    @endif
                    <p x-show="clientError" x-text="clientError" x-cloak class="error"></p>
                    @foreach ($errors->get('evidenceUploads*') as $messages)
                        @foreach ((array) $messages as $message) <p class="error">{{ $message }}</p> @endforeach
                    @endforeach
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
                                @include('livewire.partials.evidence-links', ['files' => $u->attachments])
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mt-5 flex justify-end gap-2">
                <x-btn variant="warning" icon="arrow-uturn-left" wire:click="closeUpdate">Batal</x-btn>
                <x-btn icon="check" wire:click="saveUpdate" wire:loading.attr="disabled" wire:target="evidenceUploads,saveUpdate">Simpan Progres</x-btn>
            </div>
        </div>
    </div>
@endif
