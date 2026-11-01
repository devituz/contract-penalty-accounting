<?php

namespace App\Domain\Contract;

use App\Domain\RowLock;
use App\Domain\RuleViolation;
use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\Delivery;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DeliveryService
{
    /** @param array<int, array{paid_qty?: int|null, bonus_qty?: int|null}> $items contract_line_id bo'yicha */
    public function record(Contract $contract, CarbonImmutable $date, array $items, User $actor): Delivery
    {
        return DB::transaction(function () use ($contract, $date, $items, $actor) {
            RowLock::acquire('contracts', $contract->id);
            $contract = Contract::with('lines', 'deliveries.items')->findOrFail($contract->id);

            $this->assertReceivable($contract, $date);
            $quantities = $this->validatedQuantities($contract, $items);

            $delivery = Delivery::create([
                'contract_id' => $contract->id,
                'delivered_on' => $date,
                'created_by' => $actor->id,
            ]);
            $delivery->items()->createMany($quantities);

            $contract->load('deliveries.items');

            if ($contract->status === ContractStatus::Active && $contract->isFullyDelivered()) {
                $contract->update(['status' => ContractStatus::Completed, 'completed_at' => now()]);
            }

            return $delivery;
        });
    }

    private function assertReceivable(Contract $contract, CarbonImmutable $date): void
    {
        if ($contract->isDraft()) {
            throw new RuleViolation('Partiya faqat faollashtirilgan shartnomaga kiritiladi.');
        }

        if ($date->lessThan($contract->start_date)) {
            throw new RuleViolation("Partiya sanasi shartnoma boshlanishidan ({$contract->start_date->toDateString()}) oldin bo'lishi mumkin emas.");
        }

        if ($date->greaterThan(today())) {
            throw new RuleViolation('Partiya sanasi kelajakda bo\'lishi mumkin emas.');
        }
    }

    /** @return list<array{contract_line_id: int, paid_qty: int, bonus_qty: int}> */
    private function validatedQuantities(Contract $contract, array $items): array
    {
        $balances = $contract->lineBalances()->keyBy(fn (LineBalance $balance) => $balance->line->id);
        $quantities = [];

        foreach ($items as $lineId => $item) {
            $paid = (int) ($item['paid_qty'] ?? 0);
            $bonus = (int) ($item['bonus_qty'] ?? 0);

            if ($paid === 0 && $bonus === 0) {
                continue;
            }

            $balance = $balances->get($lineId);

            if ($balance === null) {
                throw new RuleViolation('Tovar qatori bu shartnomaga tegishli emas.');
            }

            $name = $balance->line->name;

            if ($paid > $balance->paidRemaining()) {
                throw new RuleViolation("«{$name}»: pullik qoldiq {$balance->paidRemaining()} dona, kiritildi {$paid} dona.");
            }

            if ($bonus > $balance->bonusRemaining()) {
                throw new RuleViolation("«{$name}»: bonus qoldiq {$balance->bonusRemaining()} dona, kiritildi {$bonus} dona.");
            }

            $quantities[] = ['contract_line_id' => $lineId, 'paid_qty' => $paid, 'bonus_qty' => $bonus];
        }

        if ($quantities === []) {
            throw new RuleViolation('Partiyada kamida 1 dona tovar bo\'lishi kerak.');
        }

        return $quantities;
    }
}
