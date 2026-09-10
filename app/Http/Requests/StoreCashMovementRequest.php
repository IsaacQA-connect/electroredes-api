<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCashMovementRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(['INFLOW', 'OUTFLOW'])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['required', 'string', 'max:255'],
        ];
    }
}