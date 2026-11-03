<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageStock', $this->route('product')) ?? false;
    }

    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::in(['initial', 'movement'])],
            'stock_control_enabled' => ['required_if:mode,initial', 'nullable', 'boolean'],
            'quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'minimum_quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'type' => ['required_if:mode,movement', 'nullable', Rule::in(['entry', 'manual_exit', 'positive_adjustment', 'negative_adjustment'])],
            'reason' => ['required_if:mode,movement', 'nullable', 'string', 'max:500'],
        ];
    }
}
