<?php

namespace Tests\Feature;

use App\Domain\Contract\ContractService;
use App\Domain\Money;
use App\Domain\RuleViolation;
use App\Enums\ContractStatus;
use App\Enums\Role;
use App\Enums\StageCondition;
use App\Models\Contract;
use App\Models\ContractLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsContracts;
use Tests\TestCase;

class ContractLifecycleTest extends TestCase
{
    use BuildsContracts, RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'number' => 'SH-1',
            'supplier' => 'MChJ Farm',
            'start_date' => '2026-03-02',
            'term_days' => 10,
            'lines' => [['name' => 'Paratsetamol', 'paid_qty' => 1000, 'bonus_qty' => 50, 'price' => '2,50']],
            'stages' => [
                ['name' => 'Avans', 'percent' => '30', 'condition' => 'after_activation', 'due_date' => ''],
                ['name' => 'Yetkazib berish', 'percent' => '70', 'condition' => 'after_full_delivery', 'due_date' => ''],
            ],
        ], $overrides);
    }

    public function test_executor_creates_draft_through_the_form(): void
    {
        $executor = $this->user(Role::Executor);

        $this->actingAs($executor)->post(route('contracts.store'), $this->payload())->assertRedirect();

        $contract = Contract::sole();
        $this->assertSame(ContractStatus::Draft, $contract->status);
        $this->assertSame($executor->id, $contract->owner_id);
        $this->assertSame('2026-03-12', $contract->dueDate()->toDateString());
        $this->assertSame('2 500,00', $contract->paidTotal()->format());
    }

    public function test_blank_line_rows_are_ignored_so_a_draft_can_be_saved_without_lines(): void
    {
        $this->actingAs($this->user(Role::Executor))->post(route('contracts.store'), $this->payload([
            'lines' => [['name' => '', 'paid_qty' => '', 'bonus_qty' => '0', 'price' => '']],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(0, Contract::sole()->lines()->count());
    }

    public function test_half_filled_line_row_is_refused(): void
    {
        $this->actingAs($this->user(Role::Executor))->post(route('contracts.store'), $this->payload([
            'lines' => [['name' => 'Paratsetamol', 'paid_qty' => '', 'bonus_qty' => '0', 'price' => '']],
        ]))->assertSessionHasErrors(['lines.0.paid_qty', 'lines.0.price']);
    }

    public function test_validation_messages_are_in_uzbek(): void
    {
        $this->actingAs($this->user(Role::Executor))
            ->post(route('contracts.store'), $this->payload(['supplier' => '']))
            ->assertSessionHasErrors(['supplier' => 'Yetkazuvchi maydoni to\'ldirilishi shart.']);
    }

    public function test_contract_number_must_be_unique(): void
    {
        $this->draftContract(null, ['number' => 'SH-1']);

        $this->actingAs($this->user(Role::Executor))->post(route('contracts.store'), $this->payload())
            ->assertSessionHasErrors('number');
    }

    public function test_activation_without_lines_is_refused(): void
    {
        $contract = $this->draftContract(null, ['lines' => []]);

        $this->actingAs($contract->owner)->post(route('contracts.activate', $contract))
            ->assertSessionHasErrors(['rule' => 'Faollashtirish uchun kamida 1 ta tovar qatori kerak.']);

        $this->assertSame(ContractStatus::Draft, $contract->fresh()->status);
    }

    public function test_activation_requires_stages_summing_to_100_percent(): void
    {
        $contract = $this->draftContract(null, ['stages' => [
            ['name' => 'Avans', 'percent_bp' => 3000, 'condition' => StageCondition::AfterActivation, 'due_date' => null],
        ]]);

        $this->expectException(RuleViolation::class);
        $this->expectExceptionMessage('jami 100% bo\'lishi kerak');

        app(ContractService::class)->activate($contract);
    }

    public function test_activation_requires_a_paid_amount(): void
    {
        $contract = $this->draftContract(null, ['lines' => [
            ['name' => 'Faqat bonus', 'paid_qty' => 0, 'bonus_qty' => 5, 'price' => Money::zero()],
        ]]);

        $this->expectException(RuleViolation::class);
        $this->expectExceptionMessage('pullik summa noldan katta');

        app(ContractService::class)->activate($contract);
    }

    public function test_active_contract_cannot_be_activated_again(): void
    {
        $this->expectException(RuleViolation::class);

        app(ContractService::class)->activate($this->activeContract());
    }

    public function test_draft_can_be_edited_with_lines_replaced(): void
    {
        $contract = $this->draftContract();

        $this->actingAs($contract->owner)->put(route('contracts.update', $contract), $this->payload([
            'number' => $contract->number,
            'lines' => [['name' => 'Yangi', 'paid_qty' => 10, 'bonus_qty' => 0, 'price' => '5']],
        ]))->assertSessionHasNoErrors();

        $this->assertSame('50,00', $contract->fresh()->paidTotal()->format());
    }

    public function test_lines_cannot_change_after_activation_over_http(): void
    {
        $contract = $this->activeContract();

        $this->actingAs($contract->owner)->put(route('contracts.update', $contract), $this->payload([
            'number' => $contract->number,
            'lines' => [['name' => 'Xakerlik', 'paid_qty' => 1, 'bonus_qty' => 0, 'price' => '1']],
        ]))->assertForbidden();

        $this->assertSame('2 500,00', $contract->fresh()->paidTotal()->format());
    }

    public function test_lines_cannot_change_after_activation_at_model_level(): void
    {
        $line = $this->activeContract()->lines()->first();

        $this->expectException(RuleViolation::class);

        $line->update(['paid_qty' => 1]);
    }

    public function test_lines_cannot_be_added_after_activation(): void
    {
        $contract = $this->activeContract();

        $this->expectException(RuleViolation::class);

        ContractLine::create(['contract_id' => $contract->id, 'name' => 'X', 'paid_qty' => 1, 'bonus_qty' => 0, 'price' => Money::parse('1')]);
    }
}
