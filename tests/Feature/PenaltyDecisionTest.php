<?php

namespace Tests\Feature;

use App\Domain\Accounting\BudgetLedger;
use App\Domain\Accounting\PaymentSchedule;
use App\Domain\Accounting\PaymentService;
use App\Domain\Contract\DeliveryService;
use App\Domain\Money;
use App\Domain\Penalty\PenaltyCalculator;
use App\Domain\Penalty\PenaltyLedger;
use App\Enums\Role;
use App\Models\Contract;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsContracts;
use Tests\TestCase;

class PenaltyDecisionTest extends TestCase
{
    use BuildsContracts, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-03-15 12:00:00');
    }

    private function approve(Contract $contract, array $data = [])
    {
        return $this->actingAs($this->user(Role::Boss))
            ->from(route('contracts.show', $contract))
            ->post(route('contracts.penalty.approve', $contract), array_merge(['as_of' => '2026-03-15'], $data));
    }

    private function approved(Contract $contract): string
    {
        return app(PenaltyLedger::class)->approvedAmount($contract)->format();
    }

    public function test_contract_page_shows_card_and_daily_table_for_as_of_date(): void
    {
        $contract = $this->activeContract();
        $this->deliver($contract, '2026-03-14', 600);
        $this->travelTo('2026-03-16 12:00:00');

        $this->actingAs($contract->owner)->get(route('contracts.show', $contract))
            ->assertOk()
            ->assertSeeText('2026-03-12')
            ->assertSeeText('35,00')
            ->assertSeeText('1 250,00')
            ->assertSeeInOrder(['2026-03-13', '2 500,00', '12,50', '2026-03-15', '1 000,00', '5,00']);
    }

    public function test_lines_with_different_prices_are_valued_separately_and_bonus_is_ignored(): void
    {
        $contract = $this->activeContract(null, ['lines' => [
            ['name' => 'A', 'paid_qty' => 100, 'bonus_qty' => 10, 'price' => Money::parse('2,50')],
            ['name' => 'B', 'paid_qty' => 10, 'bonus_qty' => 0, 'price' => Money::parse('100')],
        ]]);
        $line = $contract->lines()->where('name', 'B')->first();

        app(DeliveryService::class)->record($contract, CarbonImmutable::parse('2026-03-13'), [
            $line->id => ['paid_qty' => 10, 'bonus_qty' => 0],
            $contract->lines()->where('name', 'A')->first()->id => ['paid_qty' => 0, 'bonus_qty' => 10],
        ], $contract->owner);

        $penalty = app(PenaltyCalculator::class)->forContract($contract->fresh(), CarbonImmutable::parse('2026-03-15'));

        // Jami 1250. 13-mart: 6,25. 14 va 15-mart: B keldi, kelmagan 250 => kuniga 1,25.
        $this->assertSame(['6,25', '1,25', '1,25'], array_map(fn ($day) => $day->penalty->format(), $penalty->days));
        $this->assertSame('8,75', $penalty->total->format());
        $this->assertSame('625,00', $penalty->limit->format());
    }

    public function test_as_of_defaults_to_today_and_can_be_changed(): void
    {
        $contract = $this->activeContract();

        $this->actingAs($contract->owner)->get(route('contracts.show', $contract))->assertSeeText('37,50');
        $this->actingAs($contract->owner)->get(route('contracts.show', [$contract, 'as_of' => '2026-03-13']))
            ->assertSeeText('12,50')->assertDontSeeText('37,50');
    }

    public function test_invalid_as_of_is_refused(): void
    {
        $contract = $this->activeContract();

        $this->actingAs($contract->owner)->get(route('contracts.show', [$contract, 'as_of' => 'kecha']))
            ->assertSessionHasErrors('as_of');
    }

    public function test_draft_contract_has_no_penalty_even_past_deadline(): void
    {
        $contract = $this->draftContract();

        $this->approve($contract)->assertSessionHasErrors(['rule' => 'Tasdiqlash uchun jarima yo\'q (hisoblangan summa 0,00).']);
    }

    public function test_no_decision_without_a_penalty(): void
    {
        $contract = $this->activeContract();

        $this->approve($contract, ['as_of' => '2026-03-10'])->assertSessionHasErrors('rule');
        $this->assertSame('0,00', $this->approved($contract));
    }

    public function test_decision_for_a_future_date_is_refused(): void
    {
        $this->approve($this->activeContract(), ['as_of' => '2026-03-16'])->assertSessionHasErrors('rule');
    }

    public function test_amount_is_recalculated_on_the_server_not_trusted_from_the_page(): void
    {
        $contract = $this->activeContract();

        $this->approve($contract, ['calculated' => '1'])->assertSessionHasNoErrors();

        $this->assertSame('37,50', $contract->penaltyDecisions()->sole()->calculated->format());
    }

    public function test_boss_cannot_approve_above_calculated_amount(): void
    {
        $contract = $this->activeContract();

        $this->approve($contract, ['amount' => '37,51'])
            ->assertSessionHasErrors(['rule' => 'Tasdiqlangan summa hisoblangan 37,50 dan oshmasligi kerak.']);
    }

    public function test_boss_cannot_approve_zero(): void
    {
        $this->approve($this->activeContract(), ['amount' => '0'])->assertSessionHasErrors('rule');
    }

    public function test_later_decision_replaces_earlier_and_history_stays(): void
    {
        $contract = $this->activeContract();
        $this->approve($contract, ['amount' => '30']);

        $this->travelTo('2026-03-17 12:00:00');
        $this->approve($contract, ['as_of' => '2026-03-17']);
        $this->assertSame('62,50', $this->approved($contract));

        $this->actingAs($this->user(Role::Boss))
            ->post(route('contracts.penalty.reject', $contract), ['as_of' => '2026-03-17']);

        $this->assertSame('0,00', $this->approved($contract));
        $this->assertSame(3, $contract->penaltyDecisions()->count());
    }

    public function test_penalty_approved_after_payments_changes_no_ledger_figures(): void
    {
        $line = $this->budget();
        $contract = $this->activeContract();
        app(PaymentService::class)->record(
            $this->stage($contract, 1), $line, Money::parse('750'),
            CarbonImmutable::parse('2026-03-15'), null, $this->user(Role::Accountant),
        );

        $this->approve($contract)->assertSessionHasNoErrors();

        $state = app(PaymentSchedule::class)->for($contract->fresh())[0];
        $this->assertSame('750,00', $state->paid->format());
        $this->assertTrue($state->remaining->isZero());
        $this->assertSame('2 250,00', app(BudgetLedger::class)->remaining($line)->format());
        $this->assertSame('37,50', $this->approved($contract));
    }

    public function test_approved_amount_stays_when_a_backdated_batch_changes_the_calculation(): void
    {
        $contract = $this->activeContract();
        $this->approve($contract);

        $this->deliver($contract, '2026-03-12', 1000);

        $this->assertSame('37,50', $this->approved($contract));
        $this->actingAs($this->user(Role::Boss))->get(route('contracts.show', [$contract, 'as_of' => '2026-03-15']))
            ->assertSeeText('Tasdiqlangan summa joriy hisobdan farq qiladi');
    }

    public function test_penalty_can_be_decided_on_completed_contract(): void
    {
        $contract = $this->activeContract();
        $this->deliver($contract, '2026-03-14', 1000);

        $this->approve($contract)->assertSessionHasNoErrors();

        $this->assertSame('25,00', $this->approved($contract));
    }
}
