<?php

namespace App\Domain\Penalty;

use App\Domain\Money;
use Carbon\CarbonImmutable;

final readonly class PenaltyBreakdown
{
    /** @param list<PenaltyDay> $days */
    public function __construct(
        public CarbonImmutable $dueDate,
        public CarbonImmutable $asOf,
        public int $delayDays,
        public Money $total,
        public Money $limit,
        public bool $limitReached,
        public array $days,
    ) {}
}
