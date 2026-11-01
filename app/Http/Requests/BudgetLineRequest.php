<?php

namespace App\Http\Requests;

use App\Models\BudgetLine;
use App\Rules\MoneyAmount;
use Illuminate\Foundation\Http\FormRequest;

class BudgetLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', BudgetLine::class);
    }

    public function rules(): array
    {
        $allocated = ['allocated' => ['required', new MoneyAmount]];

        return $this->route('budgetLine') ? $allocated : $allocated + [
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'expense_item' => ['required', 'string', 'max:255'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ];
    }

    public function attributes(): array
    {
        return [
            'allocated' => 'ajratilgan summa',
            'fiscal_year' => 'moliyaviy yil',
            'expense_item' => 'xarajat moddasi',
            'month' => 'oy',
        ];
    }
}
