<?php

namespace App\Rules;

use App\Domain\Money;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

/** Faqat yozilish shaklini tekshiradi. "Noldan katta" kabi biznes qoidalar servisda, o'z tartibida. */
class MoneyAmount implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            Money::parse((string) $value);
        } catch (InvalidArgumentException) {
            $fail('Summa noto\'g\'ri. Musbat son, ko\'pi bilan 2 xona: masalan 750,00.');
        }
    }
}
