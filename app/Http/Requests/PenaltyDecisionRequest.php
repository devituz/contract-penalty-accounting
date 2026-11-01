<?php

namespace App\Http\Requests;

use App\Rules\MoneyAmount;
use Illuminate\Foundation\Http\FormRequest;

class PenaltyDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('decidePenalty', $this->route('contract'));
    }

    public function rules(): array
    {
        return [
            'as_of' => ['required', 'date_format:Y-m-d'],
            'amount' => ['nullable', new MoneyAmount],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return ['as_of' => 'hisoblash sanasi', 'amount' => 'tasdiqlangan summa', 'note' => 'izoh'];
    }
}
