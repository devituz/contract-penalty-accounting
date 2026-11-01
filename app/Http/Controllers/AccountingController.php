<?php

namespace App\Http\Controllers;

use App\Domain\Accounting\PaymentSchedule;
use App\Domain\Accounting\PaymentService;
use App\Domain\Accounting\StageState;
use App\Domain\Money;
use App\Domain\Penalty\PenaltyLedger;
use App\Http\Requests\PaymentRequest;
use App\Http\Requests\VoidPaymentRequest;
use App\Models\BudgetLine;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\PaymentStage;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AccountingController extends Controller
{
    private const PAYMENT_RELATIONS = ['stage.contract', 'budgetLine', 'creator', 'voider'];

    public function index(PaymentSchedule $schedule, PenaltyLedger $penalties): View
    {
        Gate::authorize('viewAccounting', Contract::class);

        $rows = Contract::with('lines', 'stages', 'deliveries.items')->latest('id')->get()
            ->map(function (Contract $contract) use ($schedule, $penalties) {
                $paid = $this->paidTotal($schedule->for($contract));

                return [
                    'contract' => $contract,
                    'total' => $contract->paidTotal(),
                    'paid' => $paid,
                    'remaining' => $contract->paidTotal()->minus($paid),
                    'penalty' => $penalties->approvedAmount($contract),
                ];
            });

        return view('accounting.index', ['rows' => $rows]);
    }

    public function show(Contract $contract, PaymentSchedule $schedule, PenaltyLedger $penalties): View
    {
        Gate::authorize('viewAccounting', Contract::class);

        $contract->load('lines', 'stages', 'deliveries.items');
        $states = $schedule->for($contract);
        $paid = $this->paidTotal($states);
        $payments = $contract->payments()->with(self::PAYMENT_RELATIONS)->orderBy('payments.id')->get();

        return view('accounting.show', [
            'contract' => $contract,
            'states' => $states,
            'total' => $contract->paidTotal(),
            'paid' => $paid,
            'remaining' => $contract->paidTotal()->minus($paid),
            'penalty' => $penalties->approvedAmount($contract),
            'activePayments' => $payments->reject->isVoided()->values(),
            'voidedPayments' => $payments->filter->isVoided()->values(),
            'budgetLines' => BudgetLine::orderBy('fiscal_year')->orderBy('expense_item')->orderBy('month')->get(),
            'stageGap' => $this->stageGap($states),
        ]);
    }

    public function register(): View
    {
        Gate::authorize('viewAccounting', Contract::class);

        $payments = Payment::with(self::PAYMENT_RELATIONS)->latest('id')->get();

        return view('accounting.register', [
            'activePayments' => $payments->reject->isVoided()->values(),
            'voidedPayments' => $payments->filter->isVoided()->values(),
        ]);
    }

    public function store(PaymentRequest $request, Contract $contract, PaymentService $service): RedirectResponse
    {
        $service->record(
            PaymentStage::findOrFail($request->validated('stage_id')),
            BudgetLine::findOrFail($request->validated('budget_line_id')),
            Money::parse($request->validated('amount')),
            CarbonImmutable::parse($request->validated('paid_on')),
            $request->validated('reference'),
            $request->user(),
        );

        return redirect()->route('accounting.show', $contract)->with('status', 'To\'lov reestrga yozildi.');
    }

    public function void(VoidPaymentRequest $request, Payment $payment, PaymentService $service): RedirectResponse
    {
        $service->void($payment, $request->validated('reason'), $request->user());

        return redirect()->route('accounting.show', $payment->stage->contract_id)->with('status', 'To\'lov bekor qilindi.');
    }

    /** @param list<StageState> $states */
    private function paidTotal(array $states): Money
    {
        return array_reduce($states, fn (Money $sum, StageState $state) => $sum->plus($state->paid), Money::zero());
    }

    /**
     * Oldingi bosqichdagi to'lov bekor qilingan, keyingisida esa faol to'lov qolgan bo'lsa,
     * shu oldingi bosqichni qaytaradi: buxgalter ketma-ketlik buzilganini ko'rishi kerak.
     *
     * @param  list<StageState>  $states
     */
    private function stageGap(array $states): ?StageState
    {
        foreach ($states as $index => $state) {
            foreach (array_slice($states, $index + 1) as $later) {
                if ($state->remaining->isPositive() && $later->paid->isPositive()) {
                    return $state;
                }
            }
        }

        return null;
    }
}
