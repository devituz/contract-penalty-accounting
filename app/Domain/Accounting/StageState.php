<?php

namespace App\Domain\Accounting;

use App\Domain\Money;
use App\Enums\StageStatus;
use App\Models\PaymentStage;

/** Bosqichning reestrdan hisoblangan holati. Bazada saqlanmaydi. */
final readonly class StageState
{
    public function __construct(
        public PaymentStage $stage,
        public Money $amount,
        public Money $paid,
        public Money $remaining,
        public bool $conditionMet,
        public StageStatus $status,
    ) {}
}
