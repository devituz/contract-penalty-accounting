<?php

namespace App\Domain\Penalty;

use App\Domain\Money;
use App\Enums\PenaltyDecisionStatus;
use App\Models\Contract;
use App\Models\PenaltyDecision;

/** Buxgalteriya ko'radigan jarima: faqat oxirgi qaror tasdiqlangan bo'lsa. */
class PenaltyLedger
{
    public function latestDecision(Contract $contract): ?PenaltyDecision
    {
        return $contract->penaltyDecisions()->reorder('id', 'desc')->first();
    }

    public function approvedAmount(Contract $contract): Money
    {
        $decision = $this->latestDecision($contract);

        return $decision?->status === PenaltyDecisionStatus::Approved
            ? $decision->effectiveAmount()
            : Money::zero();
    }
}
