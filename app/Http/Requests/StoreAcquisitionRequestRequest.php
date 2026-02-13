<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAcquisitionRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source' => ['nullable', 'in:member_request,staff_manual'],
            'title' => ['required', 'string', 'max:255'],
            'author_text' => ['nullable', 'string', 'max:255'],
            'isbn' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'estimated_price' => ['nullable', 'numeric', 'min:0'],
            'book_request_id' => ['nullable', 'integer', 'exists:book_requests,id'],
        ];
    }
}
