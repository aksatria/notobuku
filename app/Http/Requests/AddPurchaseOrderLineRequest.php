<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddPurchaseOrderLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'biblio_id' => ['nullable', 'integer', 'exists:biblio,id'],
            'title' => ['required_without:biblio_id', 'string', 'max:255'],
            'author_text' => ['nullable', 'string', 'max:255'],
            'isbn' => ['nullable', 'string', 'max:32'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
