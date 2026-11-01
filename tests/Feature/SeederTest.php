<?php

namespace Tests\Feature;

use App\Domain\Penalty\PenaltyCalculator;
use App\Enums\ContractStatus;
use App\Enums\Role;
use App\Models\BudgetLine;
use App\Models\Contract;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_three_demo_users_one_per_role(): void
    {
        $this->assertSame(3, User::count());

        foreach (Role::cases() as $role) {
            $this->assertSame(1, User::where('role', $role)->count());
        }
    }

    public function test_budget_matches_the_task_table(): void
    {
        $this->assertSame(
            [3 => '3 000,00', 4 => '2 000,00'],
            BudgetLine::orderBy('month')->get()->mapWithKeys(fn ($line) => [$line->month => $line->allocated->format()])->all(),
        );
    }

    public function test_sample_contracts_reproduce_the_task_examples(): void
    {
        $calculator = app(PenaltyCalculator::class);

        $this->assertSame(ContractStatus::Draft, Contract::firstWhere('number', 'SH-2026/001')->status);

        $nothing = Contract::firstWhere('number', 'SH-2026/002');
        $this->assertSame('37,50', $calculator->forContract($nothing, CarbonImmutable::parse('2026-03-15'))->total->format());

        $partial = Contract::firstWhere('number', 'SH-2026/003');
        $this->assertSame('35,00', $calculator->forContract($partial, CarbonImmutable::parse('2026-03-16'))->total->format());
    }
}
