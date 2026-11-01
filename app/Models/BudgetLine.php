<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetLine extends Model
{
    public const MONTHS = [
        1 => 'Yanvar', 2 => 'Fevral', 3 => 'Mart', 4 => 'Aprel', 5 => 'May', 6 => 'Iyun',
        7 => 'Iyul', 8 => 'Avgust', 9 => 'Sentabr', 10 => 'Oktabr', 11 => 'Noyabr', 12 => 'Dekabr',
    ];

    protected $fillable = ['fiscal_year', 'expense_item', 'month', 'allocated'];

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'month' => 'integer',
            'allocated' => MoneyCast::class,
        ];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function monthName(): string
    {
        return self::MONTHS[$this->month];
    }

    public function label(): string
    {
        return "{$this->fiscal_year} · {$this->expense_item} · {$this->monthName()}";
    }
}
