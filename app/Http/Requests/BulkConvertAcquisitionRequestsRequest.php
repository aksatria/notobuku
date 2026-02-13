<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkConvertAcquisitionRequestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'request_ids' => ['required', 'array', 'min:1'],
            'request_ids.*' => ['integer', 'exists:acquisitions_requests,id'],
            'quantities' => ['nullable', 'array'],
            'quantities.*' => ['integer', 'min:1'],
            'po_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'currency' => ['nullable', 'string', 'max:10'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $poId = (int) $this->input('po_id', 0);
            $vendorId = (int) $this->input('vendor_id', 0);
            if ($poId <= 0 && $vendorId <= 0) {
                $validator->errors()->add('vendor_id', 'Vendor wajib dipilih jika tidak memakai draft PO.');
            }
        });
    }
}
