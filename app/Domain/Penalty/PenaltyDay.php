<?php

namespace App\Domain\Penalty;

use App\Domain\Money;
use Carbon\CarbonImmutable;

/** Kun-kunlik jadvalning bir qatori. */
final readonly class PenaltyDay
{
    public function __construct(
        public CarbonImmutable $date,
        public Money $undelivered,
        /** Shu kun uchun 0,5% bo'yicha hisoblangan summa (limitgacha). */
        public Money $accrued,
        /** Jamiga haqiqatda qo'shilgan summa: limitga yetgach kamayadi yoki 0 bo'ladi. */
        public Money $penalty,
        public Money $runningTotal,
    ) {}
}
