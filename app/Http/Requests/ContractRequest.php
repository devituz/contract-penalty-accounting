<?php

namespace App\Http\Requests;

use App\Domain\Money;
use App\Enums\StageCondition;
use App\Models\Contract;
use App\Rules\MoneyAmount;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;
use OverflowException;

class ContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        $contract = $this->route('contract');

        return $contract
            ? $this->user()->can('update', $contract)
            : $this->user()->can('create', Contract::class);
    }

    /** Formadagi to'liq bo'sh qator kiritilmagan hisoblanadi: qoralama qatorsiz ham saqlanadi. */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'lines' => array_filter(
                $this->input('lines', []),
                fn (array $line) => ($line['name'] ?? '') !== '' || ($line['paid_qty'] ?? '') !== '' || ($line['price'] ?? '') !== '',
            ),
        ]);
    }

    public function rules(): array
    {
        return [
            'number' => ['required', 'string', 'max:100', Rule::unique('contracts')->ignore($this->route('contract'))],
            'supplier' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'term_days' => ['required', 'integer', 'min:1', 'max:3650'],

            'lines' => ['nullable', 'array'],
            'lines.*.name' => ['required', 'string', 'max:255'],
            'lines.*.paid_qty' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'lines.*.bonus_qty' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'lines.*.price' => ['required', new MoneyAmount],

            'stages' => ['nullable', 'array'],
            'stages.*.name' => ['required', 'string', 'max:255'],
            'stages.*.percent' => ['required', $this->percentRule()],
            'stages.*.condition' => ['required', Rule::enum(StageCondition::class)],
            'stages.*.due_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function attributes(): array
    {
        return [
            'number' => 'shartnoma raqami',
            'supplier' => 'yetkazuvchi',
            'start_date' => 'boshlanish sanasi',
            'term_days' => 'muddat',
            'lines.*.name' => 'tovar nomi',
            'lines.*.paid_qty' => 'pullik soni',
            'lines.*.bonus_qty' => 'bonus soni',
            'lines.*.price' => 'narx',
            'stages.*.name' => 'bosqich nomi',
            'stages.*.percent' => 'foiz',
            'stages.*.condition' => 'to\'lash sharti',
            'stages.*.due_date' => 'muddat sanasi',
        ];
    }

    public function after(): array
    {
        return [fn (Validator $validator) => $this->checkLineTotals($validator)];
    }

    /** Servis uchun tayyor ma'lumot: pul Money, foiz butun basis point. */
    public function toData(): array
    {
        $data = $this->validated();

        $data['lines'] = array_map(fn (array $line) => [
            'name' => $line['name'],
            'paid_qty' => (int) $line['paid_qty'],
            'bonus_qty' => (int) ($line['bonus_qty'] ?? 0),
            'price' => Money::parse($line['price']),
        ], $data['lines'] ?? []);

        $data['stages'] = array_map(fn (array $stage) => [
            'name' => $stage['name'],
            'percent_bp' => Money::parseScaled($stage['percent']),
            'condition' => StageCondition::from($stage['condition']),
            'due_date' => $stage['due_date'] ?? null,
        ], $data['stages'] ?? []);

        return $data;
    }

    private function percentRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            try {
                $percent = Money::parseScaled((string) $value);
            } catch (InvalidArgumentException) {
                $percent = -1;
            }

            if ($percent <= 0 || $percent > 10000) {
                $fail('Foiz 0 dan katta va 100 dan oshmasligi kerak (ko\'pi bilan 2 xona).');
            }
        };
    }

    private function checkLineTotals(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        foreach ($this->input('lines', []) as $index => $line) {
            $paid = (int) $line['paid_qty'];
            $price = Money::parse((string) $line['price']);

            if ($paid === 0 && (int) ($line['bonus_qty'] ?? 0) === 0) {
                $validator->errors()->add("lines.{$index}.paid_qty", 'Qatorda pullik yoki bonus soni bo\'lishi kerak.');
            }

            if ($paid > 0 && ! $price->isPositive()) {
                $validator->errors()->add("lines.{$index}.price", 'Pullik tovar narxi noldan katta bo\'lishi kerak.');
            }

            try {
                $price->times($paid);
            } catch (OverflowException) {
                $validator->errors()->add("lines.{$index}.price", 'Qator summasi juda katta.');
            }
        }
    }
}
