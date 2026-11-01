<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('deliver', $this->route('contract'));
    }

    public function rules(): array
    {
        return [
            'delivered_on' => ['required', 'date_format:Y-m-d'],
            'items' => ['required', 'array'],
            'items.*.paid_qty' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'items.*.bonus_qty' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'delivered_on' => 'partiya sanasi',
            'items.*.paid_qty' => 'pullik miqdor',
            'items.*.bonus_qty' => 'bonus miqdor',
        ];
    }
}
