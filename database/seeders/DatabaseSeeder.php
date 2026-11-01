<?php

namespace Database\Seeders;

use App\Domain\Contract\ContractService;
use App\Domain\Contract\DeliveryService;
use App\Domain\Money;
use App\Enums\Role;
use App\Enums\StageCondition;
use App\Models\BudgetLine;
use App\Models\Contract;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    private const FISCAL_YEAR = 2026;

    public function run(ContractService $contracts, DeliveryService $deliveries): void
    {
        $executor = $this->user('Ijrochi Demo', 'ijrochi@demo.uz', Role::Executor);
        $this->user('Boshliq Demo', 'boshliq@demo.uz', Role::Boss);
        $this->user('Buxgalter Demo', 'buxgalter@demo.uz', Role::Accountant);

        foreach ([3 => '3000', 4 => '2000'] as $month => $allocated) {
            BudgetLine::create([
                'fiscal_year' => self::FISCAL_YEAR,
                'expense_item' => 'Dori vositalari',
                'month' => $month,
                'allocated' => Money::parse($allocated),
            ]);
        }

        // A misoli uchun: qoralama.
        $contracts->create($this->contract('SH-2026/001', 'MChJ Farm Servis'), $executor);

        // 1-misol: hech narsa kelmagan.
        $contracts->activate($contracts->create($this->contract('SH-2026/002', 'MChJ Medline'), $executor));

        // 2-misol: 14-mart kuni 600 dona kelgan.
        $partial = $contracts->activate($contracts->create($this->contract('SH-2026/003', 'AJ Dori Darmon'), $executor));
        $this->receive($deliveries, $partial, $executor);
    }

    private function user(string $name, string $email, Role $role): User
    {
        return User::create(['name' => $name, 'email' => $email, 'password' => 'password', 'role' => $role]);
    }

    private function contract(string $number, string $supplier): array
    {
        return [
            'number' => $number,
            'supplier' => $supplier,
            'start_date' => self::FISCAL_YEAR.'-03-02',
            'term_days' => 10,
            'lines' => [['name' => 'Paratsetamol 500 mg', 'paid_qty' => 1000, 'bonus_qty' => 50, 'price' => Money::parse('2,50')]],
            'stages' => [
                ['name' => 'Avans', 'percent_bp' => 3000, 'condition' => StageCondition::AfterActivation, 'due_date' => null],
                ['name' => 'Yetkazib berish', 'percent_bp' => 7000, 'condition' => StageCondition::AfterFullDelivery, 'due_date' => null],
            ],
        ];
    }

    private function receive(DeliveryService $deliveries, Contract $contract, User $executor): void
    {
        $deliveries->record(
            $contract,
            CarbonImmutable::parse(self::FISCAL_YEAR.'-03-14'),
            [$contract->lines()->first()->id => ['paid_qty' => 600, 'bonus_qty' => 0]],
            $executor,
        );
    }
}
