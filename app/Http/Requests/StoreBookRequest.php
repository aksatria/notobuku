<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Untuk sekarang semua user login boleh tambah.
        // Nanti bisa kita batasi ke staff/admin saja.
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'title'       => ['required','string','min:2','max:255'],
            'author'      => ['nullable','string','max:255'],
            'isbn'        => ['nullable','string','max:32'],
            'publisher'   => ['nullable','string','max:255'],
            'year'        => ['nullable','string','max:10'],
            'subject'     => ['nullable','string','max:255'],
            'call_number' => ['nullable','string','max:64'],
            'description' => ['nullable','string','max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Judul wajib diisi.',
            'title.min' => 'Judul terlalu pendek.',
            'title.max' => 'Judul terlalu panjang.',
            'isbn.max' => 'ISBN terlalu panjang.',
            'call_number.max' => 'Call number terlalu panjang.',
        ];
    }
}
