<?php

namespace App\Domain\Penalty;

use App\Domain\Money;
use App\Domain\RowLock;
use App\Domain\RuleViolation;
use App\Enums\PenaltyDecisionStatus;
use App\Models\Contract;
use App\Models\PenaltyDecision;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PenaltyService
{
    public function __construct(private PenaltyCalculator $calculator) {}

    public function approve(Contract $contract, CarbonImmutable $asOf, ?Money $amount, ?string $note, User $boss): PenaltyDecision
    {
        return $this->decide($contract, $asOf, PenaltyDecisionStatus::Approved, $amount, $note, $boss);
    }

    public function reject(Contract $contract, CarbonImmutable $asOf, ?string $note, User $boss): PenaltyDecision
    {
        return $this->decide($contract, $asOf, PenaltyDecisionStatus::Rejected, null, $note, $boss);
    }

    private function decide(Contract $contract, CarbonImmutable $asOf, PenaltyDecisionStatus $status, ?Money $amount, ?string $note, User $boss): PenaltyDecision
    {
        return DB::transaction(function () use ($contract, $asOf, $status, $amount, $note, $boss) {
            RowLock::acquire('contracts', $contract->id);
            $contract = Contract::with('lines', 'deliveries.items')->findOrFail($contract->id);

            if ($asOf->greaterThan(today())) {
                throw new RuleViolation('Hisoblash sanasi kelajakda: hali o\'tmagan kunlar uchun qaror qabul qilinmaydi.');
            }

            // Summa sahifadan olinmaydi, har doim shu yerda qayta hisoblanadi.
            $calculated = $this->calculator->forContract($contract, $asOf)->total;

            if ($calculated->isZero()) {
                throw new RuleViolation('Tasdiqlash uchun jarima yo\'q (hisoblangan summa 0,00).');
            }

            if ($amount !== null) {
                $this->assertAmountAllowed($amount, $calculated);
            }

            return PenaltyDecision::create([
                'contract_id' => $contract->id,
                'status' => $status,
                'as_of' => $asOf,
                'calculated' => $calculated,
                'approved' => $amount,
                'note' => $note,
                'decided_by' => $boss->id,
            ]);
        });
    }

    private function assertAmountAllowed(Money $amount, Money $calculated): void
    {
        if (! $amount->isPositive()) {
            throw new RuleViolation('Tasdiqlangan summa noldan katta bo\'lishi kerak. Jarimadan voz kechish uchun uni rad eting.');
        }

        if ($amount->greaterThan($calculated)) {
            throw new RuleViolation("Tasdiqlangan summa hisoblangan {$calculated->format()} dan oshmasligi kerak.");
        }
    }
}
