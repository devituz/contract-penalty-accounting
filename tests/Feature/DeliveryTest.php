<?php

namespace Tests\Feature;

use App\Domain\Contract\ContractService;
use App\Domain\Money;
use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\Delivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsContracts;
use Tests\TestCase;

class DeliveryTest extends TestCase
{
    use BuildsContracts, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-03-20 12:00:00');
    }

    private function postBatch(Contract $contract, string $date, int $paid, int $bonus)
    {
        $line = $contract->lines()->first();

        return $this->actingAs($contract->owner)
            ->from(route('contracts.show', $contract))
            ->post(route('contracts.deliveries.store', $contract), [
                'delivered_on' => $date,
                'items' => [$line->id => ['paid_qty' => $paid, 'bonus_qty' => $bonus]],
            ]);
    }

    public function test_batch_is_recorded_and_balances_are_separate(): void
    {
        $contract = $this->activeContract();

        $this->postBatch($contract, '2026-03-14', 600, 20)->assertSessionHasNoErrors();

        $balance = $contract->fresh()->lineBalances()->first();
        $this->assertSame(400, $balance->paidRemaining());
        $this->assertSame(30, $balance->bonusRemaining());
        $this->assertSame(ContractStatus::Active, $contract->fresh()->status);
    }

    public function test_paid_over_delivery_gets_a_precise_error(): void
    {
        $contract = $this->activeContract();

        $this->postBatch($contract, '2026-03-14', 1001, 0)->assertSessionHasErrors([
            'rule' => '«Paratsetamol»: pullik qoldiq 1000 dona, kiritildi 1001 dona.',
        ]);
        $this->assertSame(0, Delivery::count());
    }

    public function test_bonus_over_delivery_gets_a_precise_error(): void
    {
        $contract = $this->activeContract();

        $this->postBatch($contract, '2026-03-14', 10, 51)->assertSessionHasErrors([
            'rule' => '«Paratsetamol»: bonus qoldiq 50 dona, kiritildi 51 dona.',
        ]);
        $this->assertSame(0, Delivery::count());
    }

    public function test_bonus_cannot_cover_missing_paid_goods(): void
    {
        $contract = $this->activeContract();
        $this->deliver($contract, '2026-03-14', 1000, 50);

        $this->postBatch($contract, '2026-03-15', 0, 1)->assertSessionHasErrors('rule');
    }

    public function test_contract_completes_on_full_paid_delivery_even_if_bonus_is_missing(): void
    {
        $contract = $this->activeContract();

        $this->deliver($contract, '2026-03-10', 600);
        $this->assertSame(ContractStatus::Active, $contract->fresh()->status);

        $this->deliver($contract, '2026-03-11', 400);
        $this->assertSame(ContractStatus::Completed, $contract->fresh()->status);
        $this->assertNotNull($contract->fresh()->completed_at);
    }

    public function test_all_lines_must_arrive_before_completion(): void
    {
        $contract = $this->activeContract(null, ['lines' => [
            ['name' => 'A', 'paid_qty' => 10, 'bonus_qty' => 0, 'price' => Money::parse('1')],
            ['name' => 'B', 'paid_qty' => 5, 'bonus_qty' => 0, 'price' => Money::parse('2')],
        ]]);

        $this->deliver($contract, '2026-03-10', 10);

        $this->assertSame(ContractStatus::Active, $contract->fresh()->status);
    }

    public function test_remaining_bonus_can_arrive_after_completion(): void
    {
        $contract = $this->activeContract();
        $this->deliver($contract, '2026-03-10', 1000);

        $this->postBatch($contract, '2026-03-15', 0, 50)->assertSessionHasNoErrors();

        $this->assertSame(0, $contract->fresh()->lineBalances()->first()->bonusRemaining());
    }

    public function test_draft_contract_cannot_receive_goods(): void
    {
        $this->postBatch($this->draftContract(), '2026-03-14', 1, 0)
            ->assertSessionHasErrors(['rule' => 'Partiya faqat faollashtirilgan shartnomaga kiritiladi.']);
    }

    public function test_empty_batch_is_refused(): void
    {
        $this->postBatch($this->activeContract(), '2026-03-14', 0, 0)->assertSessionHasErrors('rule');
    }

    public function test_batch_date_before_contract_start_is_refused(): void
    {
        $this->postBatch($this->activeContract(), '2026-03-01', 1, 0)->assertSessionHasErrors('rule');
    }

    public function test_future_batch_date_is_refused(): void
    {
        $this->postBatch($this->activeContract(), '2026-03-21', 1, 0)->assertSessionHasErrors('rule');
    }

    public function test_line_of_another_contract_is_refused(): void
    {
        $contract = $this->activeContract();
        $foreign = $this->activeContract()->lines()->first();

        $this->actingAs($contract->owner)->post(route('contracts.deliveries.store', $contract), [
            'delivered_on' => '2026-03-14',
            'items' => [$foreign->id => ['paid_qty' => 1, 'bonus_qty' => 0]],
        ])->assertSessionHasErrors('rule');

        $this->assertSame(0, Delivery::count());
    }

    public function test_activation_helper_uses_service(): void
    {
        $contract = app(ContractService::class)->activate($this->draftContract());

        $this->assertNotNull($contract->activated_at);
    }
}
