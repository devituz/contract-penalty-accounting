<?php

namespace App\Domain\Accounting;

use App\Domain\Money;
use App\Domain\RowLock;
use App\Domain\RuleViolation;
use App\Models\BudgetLine;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\PaymentStage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Reestrga yozish va bekor qilish. Tekshiruvlar qulf ichida bajariladi. */
class PaymentService
{
    public function __construct(private PaymentGuard $guard) {}

    public function record(PaymentStage $stage, BudgetLine $line, Money $amount, CarbonImmutable $paidOn, ?string $reference, User $actor): Payment
    {
        return DB::transaction(function () use ($stage, $line, $amount, $paidOn, $reference, $actor) {
            $this->lock($stage->contract_id, $line->id);

            $contract = Contract::with('lines', 'stages', 'deliveries.items')->findOrFail($stage->contract_id);
            $stage = $contract->stages->firstWhere('id', $stage->id);
            $line = $line->fresh();

            $this->guard->check($contract, $stage, $line, $amount, $paidOn);

            return Payment::create([
                'payment_stage_id' => $stage->id,
                'budget_line_id' => $line->id,
                'amount' => $amount,
                'paid_on' => $paidOn,
                'reference' => $reference,
                'created_by' => $actor->id,
            ]);
        });
    }

    public function void(Payment $payment, string $reason, User $actor): Payment
    {
        // Tranzaksiya ichida birinchi amal qulf bo'lishi shart (RowLock ga qarang), shuning uchun oldindan o'qiymiz.
        $contractId = $payment->stage->contract_id;

        return DB::transaction(function () use ($payment, $contractId, $reason, $actor) {
            $this->lock($contractId, $payment->budget_line_id);

            $payment = $payment->fresh();

            if ($payment->isVoided()) {
                throw new RuleViolation('Bu to\'lov allaqachon bekor qilingan.');
            }

            $payment->update([
                'voided_at' => now(),
                'voided_by' => $actor->id,
                'void_reason' => $reason,
            ]);

            return $payment;
        });
    }

    private function lock(int $contractId, int $budgetLineId): void
    {
        RowLock::acquire('contracts', $contractId);
        RowLock::acquire('budget_lines', $budgetLineId);
    }
}
