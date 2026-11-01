<?php

namespace App\Domain\Accounting;

use App\Domain\Money;
use App\Domain\RuleViolation;
use App\Models\BudgetLine;
use App\Models\Contract;
use App\Models\PaymentStage;
use Carbon\CarbonImmutable;

/** To'lov tekshiruvlari. Tartib muhim: birinchi buzilgan qoidada to'xtaydi. */
class PaymentGuard
{
    public function __construct(
        private PaymentSchedule $schedule,
        private BudgetLedger $budget,
    ) {}

    public function check(Contract $contract, PaymentStage $stage, BudgetLine $line, Money $amount, CarbonImmutable $paidOn): void
    {
        $this->contractIsPayable($contract);

        $states = $this->schedule->for($contract);
        $index = array_search($stage->id, array_map(fn (StageState $state) => $state->stage->id, $states), true);
        $state = $states[$index];

        $this->conditionIsMet($state);
        $this->previousStagesArePaid(array_slice($states, 0, $index));
        $this->amountFitsStage($amount, $state);
        $this->dateFitsFiscalYear($paidOn, $line);
        $this->amountFitsBudget($amount, $line);
    }

    private function contractIsPayable(Contract $contract): void
    {
        if (! $contract->isPayable()) {
            throw new RuleViolation("Shartnoma faol emas (holati: {$contract->status->label()}). To'lov faqat Faol yoki Yakunlangan shartnoma bo'yicha kiritiladi.");
        }
    }

    private function conditionIsMet(StageState $state): void
    {
        if (! $state->conditionMet) {
            throw new RuleViolation("«{$state->stage->name}» bosqichining to'lash sharti bajarilmagan: {$state->stage->condition->label()}.");
        }
    }

    /** @param list<StageState> $previous */
    private function previousStagesArePaid(array $previous): void
    {
        foreach ($previous as $state) {
            if ($state->remaining->isPositive()) {
                throw new RuleViolation("«{$state->stage->name}» bosqichi to'liq to'lanmagan (qoldiq {$state->remaining->format()}). Avval uni to'lang.");
            }
        }
    }

    private function amountFitsStage(Money $amount, StageState $state): void
    {
        if (! $amount->isPositive()) {
            throw new RuleViolation('Summa noldan katta bo\'lishi kerak.');
        }

        if ($amount->greaterThan($state->remaining)) {
            throw new RuleViolation("Summa bosqichning qolgan summasidan ko'p: qoldiq {$state->remaining->format()}, kiritildi {$amount->format()}.");
        }
    }

    private function dateFitsFiscalYear(CarbonImmutable $paidOn, BudgetLine $line): void
    {
        if ($paidOn->year !== $line->fiscal_year) {
            throw new RuleViolation("To'lov sanasi ({$paidOn->toDateString()}) smeta moliyaviy yiliga mos emas: tanlangan qator {$line->fiscal_year}-yil uchun.");
        }
    }

    private function amountFitsBudget(Money $amount, BudgetLine $line): void
    {
        $remaining = $this->budget->remaining($line);

        if ($amount->greaterThan($remaining)) {
            throw new RuleViolation("Summa «{$line->label()}» smeta qoldig'idan ko'p: qoldiq {$remaining->format()}, kiritildi {$amount->format()}.");
        }
    }
}
