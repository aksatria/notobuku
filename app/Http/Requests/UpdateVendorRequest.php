<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'contact_json' => ['nullable', 'array'],
            'contact_json.*' => ['nullable'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
