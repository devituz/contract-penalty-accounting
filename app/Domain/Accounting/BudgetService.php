<?php

namespace App\Domain\Accounting;

use App\Domain\Money;
use App\Domain\RowLock;
use App\Domain\RuleViolation;
use App\Models\BudgetLine;
use Illuminate\Support\Facades\DB;

class BudgetService
{
    public function __construct(private BudgetLedger $ledger) {}

    public function create(int $fiscalYear, string $expenseItem, int $month, Money $allocated): BudgetLine
    {
        $exists = BudgetLine::where(['fiscal_year' => $fiscalYear, 'expense_item' => $expenseItem, 'month' => $month])->exists();

        if ($exists) {
            throw new RuleViolation("Bu smeta qatori allaqachon bor: {$fiscalYear} · {$expenseItem} · ".BudgetLine::MONTHS[$month].'. Summani qatorning o\'zida o\'zgartiring.');
        }

        return BudgetLine::create([
            'fiscal_year' => $fiscalYear,
            'expense_item' => $expenseItem,
            'month' => $month,
            'allocated' => $allocated,
        ]);
    }

    public function updateAllocation(BudgetLine $line, Money $allocated): BudgetLine
    {
        return DB::transaction(function () use ($line, $allocated) {
            RowLock::acquire('budget_lines', $line->id);

            $spent = $this->ledger->spent($line);

            if ($allocated->lessThan($spent)) {
                throw new RuleViolation("Ajratilgan summa to'langan {$spent->format()} dan kam bo'lishi mumkin emas.");
            }

            $line->update(['allocated' => $allocated]);

            return $line;
        });
    }
}
