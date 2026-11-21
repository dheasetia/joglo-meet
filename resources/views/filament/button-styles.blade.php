{{--
    Tombol panel /admin diseragamkan dengan <x-btn> aplikasi rapat
    (resources/views/components/btn.blade.php): solid, teks putih, animasi halus.

    Warna Filament → varian <x-btn>:
      primary, success           → primary   (emerald-700 → hover emerald-800)
      gray (bawaan), info        → secondary (slate-700   → hover slate-800)
      danger                     → danger    (rose-700    → hover rose-800)
      warning                    → warning   (amber-600   → hover amber-700)

    CSS Filament berada di @layer components, sehingga aturan tanpa layer di bawah
    ini otomatis menimpanya tanpa !important. Nilai warna = palet Tailwind v4.
--}}
<style>
    .fi-btn:not(.fi-outlined):not(label):not([aria-pressed]) {
        --btn-bg: oklch(37.2% 0.044 257.287);      /* slate-700 */
        --btn-hover: oklch(27.9% 0.041 260.031);   /* slate-800 */
        --btn-ring: oklch(44.6% 0.043 257.281);    /* slate-600 */

        background-color: var(--btn-bg);
        color: #fff;
        font-weight: 600;
        border-radius: 0.75rem;
        box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
        transition-property: transform, box-shadow, background-color, opacity;
        transition-duration: 150ms;
        transition-timing-function: cubic-bezier(0, 0, 0.2, 1);
    }

    .fi-btn:not(.fi-outlined):not(label):not([aria-pressed]):is(.fi-color-primary, .fi-color-success) {
        --btn-bg: oklch(50.8% 0.118 165.612);      /* emerald-700 */
        --btn-hover: oklch(43.2% 0.095 166.913);   /* emerald-800 */
        --btn-ring: oklch(59.6% 0.145 163.225);    /* emerald-600 */
    }

    .fi-btn:not(.fi-outlined):not(label):not([aria-pressed]).fi-color-danger {
        --btn-bg: oklch(51.4% 0.222 16.935);       /* rose-700 */
        --btn-hover: oklch(45.5% 0.188 13.697);    /* rose-800 */
        --btn-ring: oklch(58.6% 0.253 17.585);     /* rose-600 */
    }

    .fi-btn:not(.fi-outlined):not(label):not([aria-pressed]).fi-color-warning {
        --btn-bg: oklch(66.6% 0.179 58.318);       /* amber-600 */
        --btn-hover: oklch(55.5% 0.163 48.998);    /* amber-700 */
        --btn-ring: oklch(76.9% 0.188 70.08);      /* amber-500 */
    }

    .fi-btn:not(.fi-outlined):not(label):not([aria-pressed]) > .fi-icon,
    .fi-btn:not(.fi-outlined):not(label):not([aria-pressed]) .fi-loading-indicator {
        color: #fff;
    }

    .fi-btn.fi-size-xs:not(.fi-outlined):not(label):not([aria-pressed]),
    .fi-btn.fi-size-sm:not(.fi-outlined):not(label):not([aria-pressed]) {
        border-radius: 0.5rem;
    }

    /* Hover: naik sedikit + bayangan; ditekan: kembali & mengecil */
    .fi-btn:not(.fi-outlined):not(label):not([aria-pressed]):not(.fi-disabled):not([disabled]):hover {
        background-color: var(--btn-hover);
        color: #fff;
        transform: translateY(-0.125rem);
        box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
    }

    .fi-btn:not(.fi-outlined):not(label):not([aria-pressed]):not(.fi-disabled):not([disabled]):active {
        transform: translateY(0) scale(0.98);
    }

    /* Fokus keyboard: ring 2px dengan jarak 2px, warna mengikuti varian */
    .fi-btn:not(.fi-outlined):not(label):not([aria-pressed]):focus-visible {
        outline: none;
        box-shadow: 0 0 0 2px #fff, 0 0 0 4px var(--btn-ring);
    }

    .dark .fi-btn:not(.fi-outlined):not(label):not([aria-pressed]):focus-visible {
        box-shadow: 0 0 0 2px rgb(24 24 27), 0 0 0 4px var(--btn-ring);
    }

    /* Nonaktif: redup, tanpa animasi hover */
    .fi-btn:not(.fi-outlined):not(label):not([aria-pressed]):is(.fi-disabled, [disabled]):not(.fi-force-enabled) {
        opacity: 0.5;
        transform: none;
        box-shadow: none;
    }

    @media (prefers-reduced-motion: reduce) {
        .fi-btn:not(.fi-outlined):not(label):not([aria-pressed]) {
            transition: none;
        }

        .fi-btn:not(.fi-outlined):not(label):not([aria-pressed]):hover,
        .fi-btn:not(.fi-outlined):not(label):not([aria-pressed]):active {
            transform: none;
        }
    }
</style>
