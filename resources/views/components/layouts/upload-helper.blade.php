{{--
    Helper Alpine untuk unggah lampiran/bukti: cek jumlah, jenis & ukuran berkas DI BROWSER
    sebelum diunggah (pesan langsung, tanpa menunggu server), lalu unggah via Livewire dengan progres.
    Dipakai: x-data="rapatUploads({ property: 'agendaUploads', append: false })"
--}}
<script>
    window.rapatUploads = (options) => ({
        property: options.property,
        append: options.append ?? false,
        maxKb: {{ (int) config('rapat.attachments.max_kb') }},
        maxFiles: {{ (int) config('rapat.attachments.max_files') }},
        mimes: @js(config('rapat.attachments.mimes')),
        uploading: false,
        progress: 0,
        dragging: false,
        clientError: null,

        pick(fileList) {
            const files = Array.from(fileList || []);
            this.clientError = null;
            if (! files.length) return;

            const mb = (bytes) => (bytes / 1048576).toLocaleString('id-ID', { maximumFractionDigits: 1 });
            if (files.length > this.maxFiles) {
                this.clientError = `Maksimal ${this.maxFiles} berkas sekali unggah (dipilih ${files.length}).`;
                return;
            }
            const wrongType = files.find((f) => ! this.mimes.includes(f.name.split('.').pop().toLowerCase()));
            if (wrongType) {
                this.clientError = `Berkas “${wrongType.name}” tidak didukung. Gunakan PDF, Word, Excel, PowerPoint, JPG, PNG, atau WEBP.`;
                return;
            }
            const tooBig = files.find((f) => f.size > this.maxKb * 1024);
            if (tooBig) {
                this.clientError = `Berkas “${tooBig.name}” berukuran ${mb(tooBig.size)} MB, melebihi batas ${Math.round(this.maxKb / 1024)} MB.`;
                return;
            }

            this.uploading = true;
            this.progress = 0;
            this.$wire.uploadMultiple(
                this.property,
                files,
                () => { this.uploading = false; },
                () => { this.uploading = false; }, // pesan galat dari server ditampilkan oleh Blade
                (event) => { this.progress = event.detail.progress; },
                () => { this.uploading = false; },
                this.append,
            );
        },
    });
</script>
