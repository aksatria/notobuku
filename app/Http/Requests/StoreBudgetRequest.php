<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'meta_json' => ['nullable', 'array'],
        ];
    }
}
