<?php

namespace App\Livewire\Concerns;

trait IndonesianValidation
{
    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'min' => ':attribute minimal :min.',
            'max' => ':attribute maksimal :max.',
            'date' => ':attribute harus berupa tanggal.',
            'exists' => ':attribute tidak valid.',
            'integer' => ':attribute harus berupa angka.',
            'url' => ':attribute harus berupa tautan yang valid.',
            'after' => ':attribute harus setelah jam mulai.',
            'in' => ':attribute tidak valid.',
            'array' => ':attribute tidak valid.',
            'date_format' => ':attribute harus berformat jam:menit.',
            'email' => ':attribute harus berupa email yang valid.',
        ];
    }
}
