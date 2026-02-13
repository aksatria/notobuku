<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConvertAcquisitionRequestToPORequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'po_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'currency' => ['nullable', 'string', 'max:10'],
        ];
    }
}
