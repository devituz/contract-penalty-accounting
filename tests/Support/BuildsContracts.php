<?php

namespace Tests\Support;

use App\Domain\Contract\ContractService;
use App\Domain\Contract\DeliveryService;
use App\Domain\Money;
use App\Enums\Role;
use App\Enums\StageCondition;
use App\Models\BudgetLine;
use App\Models\Contract;
use App\Models\PaymentStage;
use App\Models\User;
use Carbon\CarbonImmutable;

/** Task'dagi standart shartnoma: 1000 × 2,50 + 50 bonus, 2-mart, 10 kun, 30/70 bosqich. */
trait BuildsContracts
{
    protected function user(Role $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    protected function draftContract(?User $owner = null, array $overrides = []): Contract
    {
        $data = array_merge([
            'number' => 'SH-'.fake()->unique()->numberBetween(1000, 999999),
            'supplier' => 'MChJ Farm',
            'start_date' => '2026-03-02',
            'term_days' => 10,
            'lines' => [['name' => 'Paratsetamol', 'paid_qty' => 1000, 'bonus_qty' => 50, 'price' => Money::parse('2,50')]],
            'stages' => [
                ['name' => 'Avans', 'percent_bp' => 3000, 'condition' => StageCondition::AfterActivation, 'due_date' => null],
                ['name' => 'Yetkazib berish', 'percent_bp' => 7000, 'condition' => StageCondition::AfterFullDelivery, 'due_date' => null],
            ],
        ], $overrides);

        return app(ContractService::class)->create($data, $owner ?? $this->user(Role::Executor));
    }

    protected function activeContract(?User $owner = null, array $overrides = []): Contract
    {
        return app(ContractService::class)->activate($this->draftContract($owner, $overrides));
    }

    protected function deliver(Contract $contract, string $date, int $paid, int $bonus = 0): void
    {
        $line = $contract->lines()->first();

        app(DeliveryService::class)->record(
            $contract,
            CarbonImmutable::parse($date),
            [$line->id => ['paid_qty' => $paid, 'bonus_qty' => $bonus]],
            $contract->owner,
        );
    }

    protected function budget(int $month = 3, string $allocated = '3000', int $year = 2026, string $item = 'Dori vositalari'): BudgetLine
    {
        return BudgetLine::create([
            'fiscal_year' => $year,
            'expense_item' => $item,
            'month' => $month,
            'allocated' => Money::parse($allocated),
        ]);
    }

    protected function stage(Contract $contract, int $position): PaymentStage
    {
        return $contract->stages()->where('position', $position)->firstOrFail();
    }
}
