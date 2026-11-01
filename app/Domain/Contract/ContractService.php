<?php

namespace App\Domain\Contract;

use App\Domain\RowLock;
use App\Domain\RuleViolation;
use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\PaymentStage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ContractService
{
    private const FULL_PERCENT_BP = 10000;

    public function create(array $data, User $owner): Contract
    {
        return DB::transaction(function () use ($data, $owner) {
            $contract = Contract::create([
                'number' => $data['number'],
                'supplier' => $data['supplier'],
                'start_date' => $data['start_date'],
                'term_days' => $data['term_days'],
                'status' => ContractStatus::Draft,
                'owner_id' => $owner->id,
            ]);

            $this->writeLinesAndStages($contract, $data);

            return $contract->fresh();
        });
    }

    public function update(Contract $contract, array $data): Contract
    {
        return DB::transaction(function () use ($contract, $data) {
            RowLock::acquire('contracts', $contract->id);
            $contract = Contract::with('lines', 'stages')->findOrFail($contract->id);

            if (! $contract->isDraft()) {
                throw new RuleViolation('Faqat Qoralama holatidagi shartnoma o\'zgartiriladi.');
            }

            $contract->update([
                'number' => $data['number'],
                'supplier' => $data['supplier'],
                'start_date' => $data['start_date'],
                'term_days' => $data['term_days'],
            ]);

            $contract->lines->each->delete();
            $contract->stages->each->delete();
            $this->writeLinesAndStages($contract, $data);

            return $contract->fresh();
        });
    }

    public function activate(Contract $contract): Contract
    {
        return DB::transaction(function () use ($contract) {
            RowLock::acquire('contracts', $contract->id);
            $contract = Contract::with('lines', 'stages')->findOrFail($contract->id);

            if (! $contract->isDraft()) {
                throw new RuleViolation('Faqat Qoralama holatidagi shartnoma faollashtiriladi.');
            }

            if ($contract->lines->isEmpty()) {
                throw new RuleViolation('Faollashtirish uchun kamida 1 ta tovar qatori kerak.');
            }

            if (! $contract->paidTotal()->isPositive()) {
                throw new RuleViolation('Faollashtirish uchun pullik summa noldan katta bo\'lishi kerak.');
            }

            $percent = (int) $contract->stages->sum('percent_bp');

            if ($percent !== self::FULL_PERCENT_BP) {
                $current = PaymentStage::formatPercent($percent);

                throw new RuleViolation("To'lov bosqichlari foizi jami 100% bo'lishi kerak (hozir {$current}%).");
            }

            $contract->update(['status' => ContractStatus::Active, 'activated_at' => now()]);

            return $contract->fresh();
        });
    }

    private function writeLinesAndStages(Contract $contract, array $data): void
    {
        foreach ($data['lines'] ?? [] as $line) {
            $contract->lines()->create($line);
        }

        foreach (array_values($data['stages'] ?? []) as $index => $stage) {
            $contract->stages()->create($stage + ['position' => $index + 1]);
        }
    }
}
