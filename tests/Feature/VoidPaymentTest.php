<?php

namespace Tests\Feature;

use App\Domain\Accounting\BudgetLedger;
use App\Domain\Accounting\PaymentService;
use App\Domain\Money;
use App\Domain\RuleViolation;
use App\Enums\Role;
use App\Models\BudgetLine;
use App\Models\Contract;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsContracts;
use Tests\TestCase;

class VoidPaymentTest extends TestCase
{
    use BuildsContracts, RefreshDatabase;

    private Contract $contract;

    private BudgetLine $march;

    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-03-20 12:00:00');
        $this->march = $this->budget();
        $this->contract = $this->activeContract();
        $this->payment = app(PaymentService::class)->record(
            $this->stage($this->contract, 1), $this->march, Money::parse('750'),
            CarbonImmutable::parse('2026-03-20'), 'PP-1', $this->user(Role::Accountant),
        );
    }

    public function test_reason_is_required(): void
    {
        $this->actingAs($this->user(Role::Accountant))
            ->post(route('accounting.payments.void', $this->payment), ['reason' => '   '])
            ->assertSessionHasErrors('reason');

        $this->assertFalse($this->payment->fresh()->isVoided());
    }

    public function test_record_keeps_who_when_and_why(): void
    {
        $accountant = $this->user(Role::Accountant);

        app(PaymentService::class)->void($this->payment, 'Summa xato', $accountant);

        $voided = $this->payment->fresh();
        $this->assertSame('Summa xato', $voided->void_reason);
        $this->assertSame($accountant->id, $voided->voided_by);
        $this->assertSame('2026-03-20', $voided->voided_at->toDateString());
        $this->assertSame('750,00', $voided->amount->format());
    }

    public function test_void_twice_is_refused_and_does_not_return_money_twice(): void
    {
        $service = app(PaymentService::class);
        $service->void($this->payment, 'Birinchi', $this->user(Role::Accountant));

        try {
            $service->void($this->payment->fresh(), 'Ikkinchi', $this->user(Role::Accountant));
            $this->fail('Ikkinchi marta bekor qilib bo\'lmasligi kerak');
        } catch (RuleViolation $e) {
            $this->assertStringContainsString('allaqachon bekor qilingan', $e->getMessage());
        }

        $this->assertSame('Birinchi', $this->payment->fresh()->void_reason);
        $this->assertSame('3 000,00', app(BudgetLedger::class)->remaining($this->march)->format());
    }

    public function test_voiding_after_the_budget_month_passed_returns_money_to_that_month(): void
    {
        $this->travelTo('2026-06-15 12:00:00');

        app(PaymentService::class)->void($this->payment, 'Kech topilgan xato', $this->user(Role::Accountant));

        $this->assertSame('3 000,00', app(BudgetLedger::class)->remaining($this->march)->format());

        // Qaytgan summa o'sha oy qatoridan qayta ishlatilishi mumkin: sana faqat yilga mos bo'lishi kerak.
        app(PaymentService::class)->record(
            $this->stage($this->contract, 1), $this->march, Money::parse('700'),
            CarbonImmutable::parse('2026-06-15'), 'PP-2', $this->user(Role::Accountant),
        );

        $this->assertSame('2 300,00', app(BudgetLedger::class)->remaining($this->march)->format());
    }

    public function test_model_refuses_delete(): void
    {
        $this->expectException(RuleViolation::class);

        $this->payment->delete();
    }

    public function test_model_refuses_changing_amount(): void
    {
        $this->expectException(RuleViolation::class);

        $this->payment->update(['amount' => Money::parse('1')]);
    }

    public function test_database_refuses_raw_delete(): void
    {
        $this->expectException(QueryException::class);

        DB::table('payments')->where('id', $this->payment->id)->delete();
    }

    public function test_database_refuses_raw_amount_change(): void
    {
        $this->expectException(QueryException::class);

        DB::table('payments')->where('id', $this->payment->id)->update(['amount' => 1]);
    }

    public function test_database_refuses_raw_reference_change(): void
    {
        $this->expectException(QueryException::class);

        DB::table('payments')->where('id', $this->payment->id)->update(['reference' => 'boshqa']);
    }

    public function test_database_refuses_changing_a_voided_record(): void
    {
        app(PaymentService::class)->void($this->payment, 'Xato', $this->user(Role::Accountant));

        $this->expectException(QueryException::class);

        DB::table('payments')->where('id', $this->payment->id)->update(['void_reason' => 'boshqa', 'voided_at' => null]);
    }

    public function test_active_and_voided_are_listed_separately(): void
    {
        app(PaymentService::class)->void($this->payment, 'Summa noto\'g\'ri', $this->user(Role::Accountant));

        $this->actingAs($this->user(Role::Accountant))
            ->get(route('accounting.show', $this->contract))
            ->assertOk()
            ->assertSeeInOrder(['Faol to\'lovlar', 'Faol to\'lov yo\'q', 'Bekor qilingan to\'lovlar', 'Summa noto&#039;g&#039;ri'], false);
    }
}
