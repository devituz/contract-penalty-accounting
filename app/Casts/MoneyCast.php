<?php

namespace App\Casts;

use App\Domain\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** @implements CastsAttributes<Money, Money> */
class MoneyCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        return $value === null ? null : Money::ofMinor((int) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        return $value?->minor;
    }
}
