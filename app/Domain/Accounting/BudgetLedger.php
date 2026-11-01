<?php

namespace App\Domain\Accounting;

use App\Domain\Money;
use App\Models\BudgetLine;
use App\Models\Payment;
use Illuminate\Support\Collection;

/** Smeta qoldig'i = ajratilgan − faol to'lovlar. Qoldiq saqlanmaydi, reestrdan olinadi. */
class BudgetLedger
{
    public function spent(BudgetLine $line): Money
    {
        return Money::ofMinor((int) Payment::active()->where('budget_line_id', $line->id)->sum('amount'));
    }

    public function remaining(BudgetLine $line): Money
    {
        return $line->allocated->minus($this->spent($line));
    }

    /** @return Collection<int, int> budget_line_id => tiyin */
    public function spentByLine(): Collection
    {
        return Payment::active()
            ->selectRaw('budget_line_id, SUM(amount) AS total')
            ->groupBy('budget_line_id')
            ->pluck('total', 'budget_line_id');
    }
}
