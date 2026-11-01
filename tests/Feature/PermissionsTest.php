<?php

namespace Tests\Feature;

use App\Domain\Accounting\PaymentService;
use App\Domain\Money;
use App\Enums\Role;
use App\Models\Contract;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsContracts;
use Tests\TestCase;

/** Ruxsatlar tugmani yashirish bilan emas, serverda: har bir so'rov to'g'ridan-to'g'ri yuboriladi. */
class PermissionsTest extends TestCase
{
    use BuildsContracts, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-03-20 12:00:00');
    }

    private function paymentFor(Contract $contract): Payment
    {
        return app(PaymentService::class)->record(
            $this->stage($contract, 1), $this->budget(), Money::parse('100'),
            CarbonImmutable::parse('2026-03-20'), null, $this->user(Role::Accountant),
        );
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get(route('contracts.index'))->assertRedirect(route('login'));
    }

    public function test_login_and_logout(): void
    {
        $user = $this->user(Role::Boss);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'hato'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('contracts.index'));
        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_executor_sees_only_own_contracts_in_the_list(): void
    {
        $mine = $this->draftContract(null, ['number' => 'MENIKI']);
        $this->draftContract(null, ['number' => 'BOSHQANIKI']);

        $this->actingAs($mine->owner)->get(route('contracts.index'))
            ->assertSeeText('MENIKI')->assertDontSeeText('BOSHQANIKI');
    }

    public function test_executor_cannot_open_foreign_contract_by_id(): void
    {
        $foreign = $this->activeContract();

        $this->actingAs($this->user(Role::Executor))->get(route('contracts.show', $foreign))->assertForbidden();
    }

    public function test_executor_cannot_act_on_foreign_contract(): void
    {
        $foreign = $this->draftContract();
        $executor = $this->user(Role::Executor);

        $this->actingAs($executor)->post(route('contracts.activate', $foreign))->assertForbidden();
        $this->actingAs($executor)->get(route('contracts.edit', $foreign))->assertForbidden();
        $this->actingAs($executor)->post(route('contracts.deliveries.store', $foreign), ['delivered_on' => '2026-03-14', 'items' => []])->assertForbidden();
    }

    public function test_forbidden_page_explains_itself_in_uzbek(): void
    {
        $this->actingAs($this->user(Role::Executor))->get(route('contracts.show', $this->activeContract()))
            ->assertForbidden()->assertSeeText('Bu sahifaga ruxsatingiz yo');
    }

    public function test_boss_and_accountant_see_all_contracts(): void
    {
        $contract = $this->activeContract();

        $this->actingAs($this->user(Role::Boss))->get(route('contracts.show', $contract))->assertOk();
        $this->actingAs($this->user(Role::Accountant))->get(route('contracts.show', $contract))->assertOk();
    }

    public function test_executor_cannot_touch_payments_or_budget(): void
    {
        $contract = $this->activeContract();
        $payment = $this->paymentFor($contract);
        $this->actingAs($contract->owner);

        $this->get(route('accounting.index'))->assertForbidden();
        $this->get(route('accounting.show', $contract))->assertForbidden();
        $this->get(route('budget.index'))->assertForbidden();
        $this->post(route('accounting.payments.store', $contract), [])->assertForbidden();
        $this->post(route('accounting.payments.void', $payment), ['reason' => 'x'])->assertForbidden();
        $this->post(route('budget.store'), [])->assertForbidden();

        $this->assertFalse($payment->fresh()->isVoided());
    }

    public function test_boss_cannot_touch_payments_or_budget(): void
    {
        $contract = $this->activeContract();
        $payment = $this->paymentFor($contract);
        $this->actingAs($this->user(Role::Boss));

        $this->get(route('accounting.show', $contract))->assertOk();
        $this->post(route('accounting.payments.store', $contract), [])->assertForbidden();
        $this->post(route('accounting.payments.void', $payment), ['reason' => 'x'])->assertForbidden();
        $this->post(route('budget.store'), [])->assertForbidden();
    }

    public function test_accountant_cannot_change_contract_or_penalty(): void
    {
        $draft = $this->draftContract();
        $active = $this->activeContract();
        $this->actingAs($this->user(Role::Accountant));

        $this->post(route('contracts.store'), [])->assertForbidden();
        $this->put(route('contracts.update', $draft), [])->assertForbidden();
        $this->post(route('contracts.activate', $draft))->assertForbidden();
        $this->post(route('contracts.deliveries.store', $active), [])->assertForbidden();
        $this->post(route('contracts.penalty.approve', $active), ['as_of' => '2026-03-20'])->assertForbidden();
        $this->post(route('contracts.penalty.reject', $active), ['as_of' => '2026-03-20'])->assertForbidden();

        $this->assertSame(0, $active->penaltyDecisions()->count());
    }

    public function test_executor_cannot_decide_penalty_on_own_contract(): void
    {
        $contract = $this->activeContract();

        $this->actingAs($contract->owner)
            ->post(route('contracts.penalty.approve', $contract), ['as_of' => '2026-03-20'])->assertForbidden();
    }

    public function test_boss_cannot_create_or_activate_contract(): void
    {
        $draft = $this->draftContract();
        $this->actingAs($this->user(Role::Boss));

        $this->post(route('contracts.store'), [])->assertForbidden();
        $this->post(route('contracts.activate', $draft))->assertForbidden();
    }

    public function test_buttons_are_hidden_for_roles_without_the_permission(): void
    {
        $contract = $this->activeContract();

        $this->actingAs($this->user(Role::Boss))->get(route('accounting.show', $contract))
            ->assertDontSee('To\'lovni saqlash', false);
        $this->actingAs($this->user(Role::Accountant))->get(route('accounting.show', $contract))
            ->assertSee('To\'lovni saqlash', false);
    }
}
