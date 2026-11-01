<?php

namespace App\Domain\Contract;

use App\Models\ContractLine;

/** Bitta tovar qatori bo'yicha qoldiq. Pullik va bonus alohida yuritiladi. */
final readonly class LineBalance
{
    public function __construct(
        public ContractLine $line,
        public int $paidDelivered,
        public int $bonusDelivered,
    ) {}

    public function paidRemaining(): int
    {
        return $this->line->paid_qty - $this->paidDelivered;
    }

    public function bonusRemaining(): int
    {
        return $this->line->bonus_qty - $this->bonusDelivered;
    }
}
