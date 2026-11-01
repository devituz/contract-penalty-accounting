<?php

namespace Tests\Feature;

use App\Domain\Accounting\BudgetLedger;
use App\Domain\Accounting\PaymentService;
use App\Domain\Money;
use App\Domain\RuleViolation;
use App\Enums\Role;
use App\Enums\StageCondition;
use App\Models\BudgetLine;
use App\Models\Payment;
use App\Models\PaymentStage;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsContracts;
use Tests\TestCase;

/** Besh tekshiruv: har biri alohida va tartibi. Tartibni keyingi qoida ham buzilgan holatda tekshiramiz. */
class PaymentRulesTest extends TestCase
{
    use BuildsContracts, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-03-20 12:00:00');
    }

    private function record(PaymentStage $stage, BudgetLine $line, string $amount, string $date = '2026-03-20'): Payment
    {
        return app(PaymentService::class)->record(
            $stage, $line, Money::parse($amount), CarbonImmutable::parse($date), 'PP-1', $this->user(Role::Accountant),
        );
    }

    private function assertRejected(string $fragment, callable $action): void
    {
        try {
            $action();
        } catch (RuleViolation $e) {
            $this->assertStringContainsString($fragment, $e->getMessage());

            return;
        }

        $this->fail('To\'lov rad etilishi kerak edi: '.$fragment);
    }

    public function test_rule_1_draft_is_reported_before_every_other_problem(): void
    {
        $contract = $this->draftContract();
        $line = $this->budget(3, '1', 2025);

        $this->assertRejected('Shartnoma faol emas', fn () => $this->record($this->stage($contract, 2), $line, '0', '2030-01-01'));
    }

    public function test_rule_2_unmet_condition_comes_before_previous_stage_and_amount(): void
    {
        $contract = $this->activeContract();

        $this->assertRejected('to\'lash sharti bajarilmagan', fn () => $this->record($this->stage($contract, 2), $this->budget(), '9999'));
    }

    public function test_rule_3_previous_stage_comes_before_amount_and_budget(): void
    {
        $contract = $this->activeContract();
        $this->deliver($contract, '2026-03-10', 1000);

        $this->assertRejected('«Avans» bosqichi to\'liq to\'lanmagan', fn () => $this->record($this->stage($contract, 2), $this->budget(3, '1'), '9999'));
    }

    public function test_rule_4_zero_amount_comes_before_budget(): void
    {
        $contract = $this->activeContract();

        $this->assertRejected('noldan katta', fn () => $this->record($this->stage($contract, 1), $this->budget(3, '1', 2025), '0'));
    }

    public function test_rule_4_amount_above_stage_remainder_comes_before_budget(): void
    {
        $contract = $this->activeContract();

        $this->assertRejected('qolgan summasidan ko\'p', fn () => $this->record($this->stage($contract, 1), $this->budget(3, '1', 2025), '750,01'));
    }

    public function test_rule_5_date_outside_fiscal_year(): void
    {
        $contract = $this->activeContract();

        $this->assertRejected('moliyaviy yiliga mos emas', fn () => $this->record($this->stage($contract, 1), $this->budget(3, '3000', 2025), '750'));
    }

    public function test_rule_5_amount_above_month_remainder(): void
    {
        $contract = $this->activeContract();

        $this->assertRejected('smeta qoldig\'idan ko\'p', fn () => $this->record($this->stage($contract, 1), $this->budget(3, '749,99'), '750'));
    }

    public function test_the_last_tiyin_of_stage_and_budget_can_be_spent(): void
    {
        $line = $this->budget(3, '750');
        $contract = $this->activeContract();

        $this->record($this->stage($contract, 1), $line, '750');

        $this->assertTrue(app(BudgetLedger::class)->remaining($line)->isZero());
    }

    public function test_payment_may_use_another_month_of_the_same_fiscal_year(): void
    {
        $april = $this->budget(4, '2000');
        $contract = $this->activeContract();

        $this->record($this->stage($contract, 1), $april, '750', '2026-03-20');

        $this->assertSame('1 250,00', app(BudgetLedger::class)->remaining($april)->format());
    }

    public function test_completed_contract_still_accepts_payments(): void
    {
        $contract = $this->activeContract();
        $line = $this->budget();
        $this->deliver($contract, '2026-03-10', 1000);

        $this->record($this->stage($contract, 1), $line, '750');
        $this->record($this->stage($contract, 2), $line, '1750');

        $this->assertSame('500,00', app(BudgetLedger::class)->remaining($line)->format());
    }

    public function test_voiding_an_advance_blocks_next_stage_until_paid_again(): void
    {
        $contract = $this->activeContract();
        $line = $this->budget();
        $this->deliver($contract, '2026-03-10', 1000);
        $advance = $this->record($this->stage($contract, 1), $line, '750');
        app(PaymentService::class)->void($advance, 'Xato', $this->user(Role::Accountant));

        $this->assertRejected('«Avans» bosqichi to\'liq to\'lanmagan', fn () => $this->record($this->stage($contract, 2), $line, '100'));
    }

    public function test_three_way_stages_with_rounding_add_up_to_contract_total(): void
    {
        $contract = $this->activeContract(null, [
            'lines' => [['name' => 'A', 'paid_qty' => 1, 'bonus_qty' => 0, 'price' => Money::parse('100')]],
            'stages' => [
                ['name' => '1', 'percent_bp' => 3333, 'condition' => StageCondition::AfterActivation, 'due_date' => null],
                ['name' => '2', 'percent_bp' => 3333, 'condition' => StageCondition::AfterActivation, 'due_date' => null],
                ['name' => '3', 'percent_bp' => 3334, 'condition' => StageCondition::AfterActivation, 'due_date' => null],
            ],
        ]);

        $line = $this->budget(3, '100');
        foreach ([1 => '33,33', 2 => '33,33', 3 => '33,34'] as $position => $amount) {
            $this->record($this->stage($contract, $position), $line, $amount);
        }

        $this->assertTrue(app(BudgetLedger::class)->remaining($line)->isZero());
    }

    public function test_stage_of_another_contract_is_refused_over_http(): void
    {
        $line = $this->budget();
        $mine = $this->activeContract();
        $other = $this->activeContract();

        $this->actingAs($this->user(Role::Accountant))
            ->post(route('accounting.payments.store', $mine), [
                'stage_id' => $this->stage($other, 1)->id,
                'amount' => '750',
                'paid_on' => '2026-03-20',
                'budget_line_id' => $line->id,
            ])->assertSessionHasErrors('stage_id');

        $this->assertSame(0, Payment::count());
    }

    public function test_malformed_money_gets_a_field_error(): void
    {
        $line = $this->budget();
        $contract = $this->activeContract();

        $this->actingAs($this->user(Role::Accountant))
            ->post(route('accounting.payments.store', $contract), [
                'stage_id' => $this->stage($contract, 1)->id,
                'amount' => '12,345',
                'paid_on' => '2026-03-20',
                'budget_line_id' => $line->id,
            ])->assertSessionHasErrors('amount');
    }
}
