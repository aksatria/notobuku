<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vendor_id' => ['required', 'integer', 'exists:vendors,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'currency' => ['nullable', 'string', 'max:10'],
        ];
    }
}
