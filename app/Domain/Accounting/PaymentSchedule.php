<?php

namespace App\Domain\Accounting;

use App\Domain\Money;
use App\Enums\StageCondition;
use App\Enums\StageStatus;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\PaymentStage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Bosqich summasi, to'langani, qoldig'i va holati. Hammasi reestrdan qayta hisoblanadi. */
class PaymentSchedule
{
    /** @return list<StageState> pozitsiya tartibida */
    public function for(Contract $contract, ?CarbonImmutable $today = null): array
    {
        $today ??= today()->toImmutable();
        $stages = $contract->stages;
        $amounts = $this->stageAmounts($contract, $stages);
        $paidByStage = $this->paidByStage($stages);
        $states = [];

        foreach ($stages as $index => $stage) {
            $paid = Money::ofMinor((int) ($paidByStage[$stage->id] ?? 0));
            $remaining = $amounts[$index]->minus($paid);
            $conditionMet = $this->conditionMet($contract, $stage);

            $states[] = new StageState(
                $stage,
                $amounts[$index],
                $paid,
                $remaining,
                $conditionMet,
                $this->status($contract, $stage, $paid, $remaining, $conditionMet, $today),
            );
        }

        return $states;
    }

    /**
     * Foiz yaxlitlanadi, shuning uchun 100% bo'lganda oxirgi bosqich qolgan summani oladi:
     * bosqichlar yig'indisi pullik summaga tiyinigacha teng bo'ladi.
     *
     * @return list<Money>
     */
    private function stageAmounts(Contract $contract, Collection $stages): array
    {
        $total = $contract->paidTotal();
        $amounts = $stages->map(fn (PaymentStage $stage) => $total->percent($stage->percent_bp))->all();

        if ((int) $stages->sum('percent_bp') === 10000) {
            $last = array_key_last($amounts);
            $others = array_reduce(array_slice($amounts, 0, $last), fn (Money $sum, Money $amount) => $sum->plus($amount), Money::zero());
            $amounts[$last] = $total->minus($others);
        }

        return $amounts;
    }

    private function paidByStage(Collection $stages): Collection
    {
        return Payment::active()
            ->whereIn('payment_stage_id', $stages->pluck('id'))
            ->selectRaw('payment_stage_id, SUM(amount) AS total')
            ->groupBy('payment_stage_id')
            ->pluck('total', 'payment_stage_id');
    }

    private function conditionMet(Contract $contract, PaymentStage $stage): bool
    {
        return match ($stage->condition) {
            StageCondition::AfterActivation => $contract->isPayable(),
            StageCondition::AfterFullDelivery => $contract->isPayable() && $contract->isFullyDelivered(),
        };
    }

    private function status(Contract $contract, PaymentStage $stage, Money $paid, Money $remaining, bool $conditionMet, CarbonImmutable $today): StageStatus
    {
        if ($contract->isDraft()) {
            return StageStatus::Planned;
        }

        if (! $remaining->isPositive()) {
            return StageStatus::Paid;
        }

        if ($stage->due_date?->lessThan($today)) {
            return StageStatus::Overdue;
        }

        if ($paid->isPositive()) {
            return StageStatus::PartiallyPaid;
        }

        return $conditionMet ? StageStatus::Ready : StageStatus::Planned;
    }
}
