<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CloseCashRegisterRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'actual_balance' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}