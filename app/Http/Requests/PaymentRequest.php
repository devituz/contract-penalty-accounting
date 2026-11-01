<?php

namespace App\Http\Requests;

use App\Models\Contract;
use App\Rules\MoneyAmount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pay', Contract::class);
    }

    public function rules(): array
    {
        return [
            'stage_id' => ['required', 'integer', Rule::exists('payment_stages', 'id')->where('contract_id', $this->route('contract')->id)],
            'budget_line_id' => ['required', 'integer', 'exists:budget_lines,id'],
            'amount' => ['required', new MoneyAmount],
            'paid_on' => ['required', 'date_format:Y-m-d'],
            'reference' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'stage_id' => 'bosqich',
            'budget_line_id' => 'smeta qatori',
            'amount' => 'summa',
            'paid_on' => 'to\'lov sanasi',
            'reference' => 'to\'lov raqami',
        ];
    }
}
