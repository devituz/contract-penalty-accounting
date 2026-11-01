<?php

namespace Tests\Feature;

use App\Domain\Accounting\PaymentSchedule;
use App\Domain\Accounting\PaymentService;
use App\Domain\Money;
use App\Enums\Role;
use App\Enums\StageCondition;
use App\Enums\StageStatus;
use App\Models\Contract;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsContracts;
use Tests\TestCase;

class StageStatusTest extends TestCase
{
    use BuildsContracts, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-03-20 12:00:00');
    }

    private function stageStatus(Contract $contract, int $position): StageStatus
    {
        return app(PaymentSchedule::class)->for($contract->fresh())[$position - 1]->status;
    }

    private function pay(Contract $contract, string $amount, int $position = 1): Payment
    {
        return app(PaymentService::class)->record(
            $this->stage($contract, $position), $this->budget(3, '3000', 2026, 'M'.uniqid()), Money::parse($amount),
            CarbonImmutable::parse('2026-03-20'), null, $this->user(Role::Accountant),
        );
    }

    private function withDueDate(string $date): array
    {
        return ['stages' => [
            ['name' => 'Avans', 'percent_bp' => 10000, 'condition' => StageCondition::AfterActivation, 'due_date' => $date],
        ]];
    }

    public function test_draft_contract_stages_are_planned(): void
    {
        $this->assertSame(StageStatus::Planned, $this->stageStatus($this->draftContract(), 1));
    }

    public function test_activation_makes_advance_ready_and_delivery_stage_stays_planned(): void
    {
        $contract = $this->activeContract();

        $this->assertSame(StageStatus::Ready, $this->stageStatus($contract, 1));
        $this->assertSame(StageStatus::Planned, $this->stageStatus($contract, 2));
    }

    public function test_full_delivery_makes_delivery_stage_ready(): void
    {
        $contract = $this->activeContract();
        $this->deliver($contract, '2026-03-10', 1000);

        $this->assertSame(StageStatus::Ready, $this->stageStatus($contract, 2));
    }

    public function test_partial_and_full_payment(): void
    {
        $contract = $this->activeContract();

        $this->pay($contract, '100');
        $this->assertSame(StageStatus::PartiallyPaid, $this->stageStatus($contract, 1));

        $this->pay($contract, '650');
        $this->assertSame(StageStatus::Paid, $this->stageStatus($contract, 1));
    }

    public function test_overdue_when_due_date_passed_with_remainder(): void
    {
        $this->assertSame(StageStatus::Overdue, $this->stageStatus($this->activeContract(null, $this->withDueDate('2026-03-19')), 1));
    }

    public function test_due_date_today_is_not_overdue(): void
    {
        $this->assertSame(StageStatus::Ready, $this->stageStatus($this->activeContract(null, $this->withDueDate('2026-03-20')), 1));
    }

    public function test_partially_paid_past_due_is_overdue(): void
    {
        $contract = $this->activeContract(null, $this->withDueDate('2026-03-19'));
        $this->pay($contract, '100');

        $this->assertSame(StageStatus::Overdue, $this->stageStatus($contract, 1));
    }

    public function test_fully_paid_past_due_is_paid(): void
    {
        $contract = $this->activeContract(null, $this->withDueDate('2026-03-19'));
        $this->pay($contract, '2500');

        $this->assertSame(StageStatus::Paid, $this->stageStatus($contract, 1));
    }

    public function test_draft_past_due_stays_planned(): void
    {
        $this->assertSame(StageStatus::Planned, $this->stageStatus($this->draftContract(null, $this->withDueDate('2026-03-19')), 1));
    }

    public function test_status_follows_the_ledger_after_void(): void
    {
        $contract = $this->activeContract();
        $payment = $this->pay($contract, '750');
        $this->assertSame(StageStatus::Paid, $this->stageStatus($contract, 1));

        app(PaymentService::class)->void($payment, 'Xato', $this->user(Role::Accountant));

        $this->assertSame(StageStatus::Ready, $this->stageStatus($contract, 1));
    }

    public function test_stage_amounts_come_from_percent_of_paid_total_and_ignore_bonus(): void
    {
        $states = app(PaymentSchedule::class)->for($this->activeContract());

        $this->assertSame('750,00', $states[0]->amount->format());
        $this->assertSame('1 750,00', $states[1]->amount->format());
    }
}
