<?php

namespace Tests\Feature;

use App\Domain\Accounting\BudgetLedger;
use App\Domain\Accounting\PaymentSchedule;
use App\Domain\Contract\ContractService;
use App\Domain\Money;
use App\Domain\Penalty\PenaltyLedger;
use App\Enums\PenaltyDecisionStatus;
use App\Enums\Role;
use App\Enums\StageStatus;
use App\Models\Contract;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsContracts;
use Tests\TestCase;

/** Task'dagi A–D misollari brauzerdagi yo'l bilan: HTTP so'rov orqali. */
class AccountingExamplesTest extends TestCase
{
    use BuildsContracts, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-03-20 12:00:00');
    }

    private function pay(Contract $contract, int $position, string $amount, $budget, string $date = '2026-03-20')
    {
        return $this->from(route('accounting.show', $contract))->post(route('accounting.payments.store', $contract), [
            'stage_id' => $this->stage($contract, $position)->id,
            'amount' => $amount,
            'paid_on' => $date,
            'budget_line_id' => $budget->id,
            'reference' => 'PP-1',
        ]);
    }

    private function stageState(Contract $contract, int $position)
    {
        return app(PaymentSchedule::class)->for($contract->fresh())[$position - 1];
    }

    public function test_example_a_advance_is_refused_on_draft_then_passes_after_activation(): void
    {
        $march = $this->budget();
        $contract = $this->draftContract();
        $this->actingAs($this->user(Role::Accountant));

        $this->pay($contract, 1, '750', $march)
            ->assertSessionHasErrors(['rule' => 'Shartnoma faol emas (holati: Qoralama). To\'lov faqat Faol yoki Yakunlangan shartnoma bo\'yicha kiritiladi.']);
        $this->assertSame(0, Payment::count());

        app(ContractService::class)->activate($contract);

        $this->pay($contract, 1, '750', $march)->assertSessionHasNoErrors();

        $this->assertSame('2 250,00', app(BudgetLedger::class)->remaining($march)->format());
        $this->assertSame(StageStatus::Paid, $this->stageState($contract, 1)->status);
    }

    public function test_example_b_delivery_stage_is_paid_in_parts_with_overpayment_refused(): void
    {
        $march = $this->budget();
        $contract = $this->activeContract();
        $this->actingAs($this->user(Role::Accountant));
        $this->pay($contract, 1, '750', $march)->assertSessionHasNoErrors();

        $this->deliver($contract, '2026-03-10', 900);
        $this->pay($contract, 2, '1000', $march)->assertSessionHasErrors('rule');
        $this->assertSame(1, Payment::count());

        $this->deliver($contract, '2026-03-11', 100);
        $this->pay($contract, 2, '1000', $march)->assertSessionHasNoErrors();

        $state = $this->stageState($contract, 2);
        $this->assertSame(StageStatus::PartiallyPaid, $state->status);
        $this->assertSame('750,00', $state->remaining->format());
        $this->assertSame('1 250,00', app(BudgetLedger::class)->remaining($march)->format());

        $this->pay($contract, 2, '800', $march)
            ->assertSessionHasErrors(['rule' => 'Summa bosqichning qolgan summasidan ko\'p: qoldiq 750,00, kiritildi 800,00.']);

        $this->pay($contract, 2, '750', $march)->assertSessionHasNoErrors();
        $this->assertSame(StageStatus::Paid, $this->stageState($contract, 2)->status);
        $this->assertSame('500,00', app(BudgetLedger::class)->remaining($march)->format());
    }

    public function test_example_c_wrong_payment_is_voided_then_corrected(): void
    {
        $march = $this->budget();
        $contract = $this->activeContract();
        $this->deliver($contract, '2026-03-10', 1000);
        $this->actingAs($this->user(Role::Accountant));
        $this->pay($contract, 1, '750', $march);
        $this->pay($contract, 2, '1000', $march);
        $wrong = Payment::where('amount', 100000)->sole();

        $this->post(route('accounting.payments.void', $wrong), ['reason' => '900 o\'rniga adashib kiritilgan'])
            ->assertSessionHasNoErrors();

        $wrong->refresh();
        $this->assertTrue($wrong->isVoided());
        $this->assertSame('900 o\'rniga adashib kiritilgan', $wrong->void_reason);
        $this->assertSame('1 750,00', $this->stageState($contract, 2)->remaining->format());
        $this->assertSame('2 250,00', app(BudgetLedger::class)->remaining($march)->format());

        $this->pay($contract, 2, '900', $march)->assertSessionHasNoErrors();

        $this->assertSame('850,00', $this->stageState($contract, 2)->remaining->format());
        $this->assertSame('1 350,00', app(BudgetLedger::class)->remaining($march)->format());
        $this->assertSame(3, Payment::count());
    }

    public function test_example_d_only_approved_penalty_reaches_accounting(): void
    {
        $this->travelTo('2026-03-15 12:00:00');
        $contract = $this->activeContract();
        $boss = $this->user(Role::Boss);
        $accountant = $this->user(Role::Accountant);
        $ledger = app(PenaltyLedger::class);

        $this->actingAs($accountant)->get(route('accounting.show', $contract))
            ->assertOk()->assertSeeText('Tasdiqlangan jarima')->assertSeeText('0,00');
        $this->assertSame('0,00', $ledger->approvedAmount($contract)->format());

        $this->actingAs($boss)->post(route('contracts.penalty.approve', $contract), ['as_of' => '2026-03-15'])
            ->assertSessionHasNoErrors();

        $this->assertSame('37,50', $ledger->approvedAmount($contract)->format());
        $this->actingAs($accountant)->get(route('accounting.show', $contract))->assertSeeText('37,50');

        $this->actingAs($boss)->post(route('contracts.penalty.approve', $contract), ['as_of' => '2026-03-15', 'amount' => '30,00'])
            ->assertSessionHasNoErrors();

        $this->assertSame('30,00', $ledger->approvedAmount($contract)->format());
        $this->actingAs($accountant)->get(route('accounting.show', $contract))
            ->assertSeeText('30,00')->assertDontSeeText('37,50');
    }

    public function test_rejected_penalty_is_not_visible_to_accounting(): void
    {
        $this->travelTo('2026-03-15 12:00:00');
        $contract = $this->activeContract();

        $this->actingAs($this->user(Role::Boss))
            ->post(route('contracts.penalty.reject', $contract), ['as_of' => '2026-03-15', 'note' => 'Kechikish bizning aybimiz bilan'])
            ->assertSessionHasNoErrors();

        $this->assertSame(PenaltyDecisionStatus::Rejected, $contract->penaltyDecisions()->sole()->status);
        $this->assertSame('0,00', app(PenaltyLedger::class)->approvedAmount($contract)->format());
    }

    public function test_money_entered_with_comma_or_dot_is_identical(): void
    {
        $march = $this->budget();
        $contract = $this->activeContract();
        $this->actingAs($this->user(Role::Accountant));

        $this->pay($contract, 1, '100,10', $march)->assertSessionHasNoErrors();
        $this->pay($contract, 1, '100.20', $march)->assertSessionHasNoErrors();

        $this->assertSame(['100,10', '100,20'], Payment::orderBy('id')->get()->map(fn ($p) => $p->amount->format())->all());
        $this->assertTrue($this->stageState($contract, 1)->paid->equals(Money::parse('200,30')));
    }
}
