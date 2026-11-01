<?php

namespace App\Domain\Penalty;

use App\Domain\Money;
use App\Enums\ContractStatus;
use App\Models\Contract;
use Carbon\CarbonImmutable;

/** Jarima qoidalari. Baza va HTTP'ga bog'liq emas: faqat sana va summalar bilan ishlaydi. */
class PenaltyCalculator
{
    public const DAILY_RATE_BP = 50;      // 0,5%

    public const LIMIT_RATE_BP = 5000;    // 50%

    public function forContract(Contract $contract, CarbonImmutable $asOf): PenaltyBreakdown
    {
        $dueDate = $contract->dueDate();

        if ($contract->status === ContractStatus::Draft) {
            return new PenaltyBreakdown($dueDate, $asOf, 0, Money::zero(), Money::zero(), false, []);
        }

        return $this->calculate($dueDate, $contract->paidTotal(), $contract->paidReceipts(), $asOf);
    }

    /** @param list<array{date: CarbonImmutable, value: Money}> $receipts kelgan pullik tovar qiymati */
    public function calculate(CarbonImmutable $dueDate, Money $paidTotal, array $receipts, CarbonImmutable $asOf): PenaltyBreakdown
    {
        $firstLateDay = $dueDate->addDay();

        // Partiya o'z sanasidan keyingi kundan hisobga olinadi, shuning uchun
        // birinchi kechikkan kundagi qoldiq = muddat kuni oxiridagi qoldiq.
        $limit = $this->undeliveredOn($firstLateDay, $paidTotal, $receipts)->percent(self::LIMIT_RATE_BP);

        $days = [];
        $total = Money::zero();

        for ($day = $firstLateDay; $day->lessThanOrEqualTo($asOf); $day = $day->addDay()) {
            $undelivered = $this->undeliveredOn($day, $paidTotal, $receipts);

            if ($undelivered->isZero()) {
                break;
            }

            $accrued = $undelivered->percent(self::DAILY_RATE_BP);
            $penalty = $accrued->min($limit->minus($total));
            $total = $total->plus($penalty);

            $days[] = new PenaltyDay($day, $undelivered, $accrued, $penalty, $total);
        }

        return new PenaltyBreakdown(
            $dueDate,
            $asOf,
            count($days),
            $total,
            $limit,
            $days !== [] && $total->equals($limit),
            $days,
        );
    }

    /** @param list<array{date: CarbonImmutable, value: Money}> $receipts */
    private function undeliveredOn(CarbonImmutable $day, Money $paidTotal, array $receipts): Money
    {
        $undelivered = $paidTotal;

        foreach ($receipts as $receipt) {
            if ($receipt['date']->lessThan($day)) {
                $undelivered = $undelivered->minus($receipt['value']);
            }
        }

        return $undelivered;
    }
}
