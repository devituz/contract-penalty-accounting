<?php

namespace Tests\Feature;

use App\Domain\Accounting\BudgetLedger;
use App\Domain\Accounting\PaymentService;
use App\Domain\Money;
use App\Enums\Role;
use App\Models\BudgetLine;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsContracts;
use Tests\TestCase;

class BudgetTest extends TestCase
{
    use BuildsContracts, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-03-20 12:00:00');
    }

    public function test_accountant_adds_a_budget_line(): void
    {
        $this->actingAs($this->user(Role::Accountant))->post(route('budget.store'), [
            'fiscal_year' => 2026, 'expense_item' => 'Dori vositalari', 'month' => 3, 'allocated' => '3000,00',
        ])->assertSessionHasNoErrors();

        $this->assertSame('3 000,00', BudgetLine::sole()->allocated->format());
    }

    public function test_same_year_item_and_month_cannot_repeat(): void
    {
        $this->budget();

        $this->actingAs($this->user(Role::Accountant))->post(route('budget.store'), [
            'fiscal_year' => 2026, 'expense_item' => 'Dori vositalari', 'month' => 3, 'allocated' => '1',
        ])->assertSessionHasErrors('rule');
    }

    public function test_allocation_cannot_drop_below_spent(): void
    {
        $line = $this->budget();
        $contract = $this->activeContract();
        app(PaymentService::class)->record(
            $this->stage($contract, 1), $line, Money::parse('750'),
            CarbonImmutable::parse('2026-03-20'), null, $this->user(Role::Accountant),
        );

        $accountant = $this->user(Role::Accountant);

        $this->actingAs($accountant)->put(route('budget.update', $line), ['allocated' => '749,99'])
            ->assertSessionHasErrors(['rule' => 'Ajratilgan summa to\'langan 750,00 dan kam bo\'lishi mumkin emas.']);

        $this->actingAs($accountant)->put(route('budget.update', $line), ['allocated' => '750'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(app(BudgetLedger::class)->remaining($line->fresh())->isZero());
    }

    public function test_budget_page_shows_ledger_based_figures(): void
    {
        $line = $this->budget();
        $contract = $this->activeContract();
        app(PaymentService::class)->record(
            $this->stage($contract, 1), $line, Money::parse('750'),
            CarbonImmutable::parse('2026-03-20'), null, $this->user(Role::Accountant),
        );

        $this->actingAs($this->user(Role::Accountant))->get(route('budget.index'))
            ->assertOk()->assertSeeInOrder(['3 000,00', '750,00', '2 250,00']);
    }
}
