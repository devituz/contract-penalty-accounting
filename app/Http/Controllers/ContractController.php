<?php

namespace App\Http\Controllers;

use App\Domain\Accounting\PaymentSchedule;
use App\Domain\Contract\ContractService;
use App\Domain\Penalty\PenaltyCalculator;
use App\Domain\Penalty\PenaltyLedger;
use App\Enums\PenaltyDecisionStatus;
use App\Enums\StageCondition;
use App\Http\Requests\ContractRequest;
use App\Models\Contract;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ContractController extends Controller
{
    public function index(Request $request): View
    {
        return view('contracts.index', [
            'contracts' => Contract::visibleTo($request->user())->with('lines')->latest('id')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Contract::class);

        return view('contracts.form', [
            'contract' => new Contract,
            'lines' => [['name' => '', 'paid_qty' => '', 'bonus_qty' => 0, 'price' => '']],
            'stages' => [
                ['name' => 'Avans', 'percent' => '30', 'condition' => StageCondition::AfterActivation->value, 'due_date' => ''],
                ['name' => 'Yetkazib berish', 'percent' => '70', 'condition' => StageCondition::AfterFullDelivery->value, 'due_date' => ''],
            ],
        ]);
    }

    public function store(ContractRequest $request, ContractService $service): RedirectResponse
    {
        $contract = $service->create($request->toData(), $request->user());

        return redirect()->route('contracts.show', $contract)->with('status', 'Shartnoma qoralama sifatida saqlandi.');
    }

    public function edit(Contract $contract): View
    {
        Gate::authorize('update', $contract);

        return view('contracts.form', [
            'contract' => $contract,
            'lines' => $contract->lines->map(fn ($line) => [
                'name' => $line->name,
                'paid_qty' => $line->paid_qty,
                'bonus_qty' => $line->bonus_qty,
                'price' => $line->price->plain(),
            ])->all(),
            'stages' => $contract->stages->map(fn ($stage) => [
                'name' => $stage->name,
                'percent' => rtrim($stage->percentLabel(), '%'),
                'condition' => $stage->condition->value,
                'due_date' => $stage->due_date?->toDateString(),
            ])->all(),
        ]);
    }

    public function update(ContractRequest $request, Contract $contract, ContractService $service): RedirectResponse
    {
        $service->update($contract, $request->toData());

        return redirect()->route('contracts.show', $contract)->with('status', 'Shartnoma yangilandi.');
    }

    public function activate(Contract $contract, ContractService $service): RedirectResponse
    {
        Gate::authorize('activate', $contract);

        $service->activate($contract);

        return redirect()->route('contracts.show', $contract)->with('status', 'Shartnoma faollashtirildi.');
    }

    public function show(Request $request, Contract $contract, PenaltyCalculator $calculator, PenaltyLedger $ledger, PaymentSchedule $schedule): View
    {
        Gate::authorize('view', $contract);

        $validated = $request->validate(
            ['as_of' => ['nullable', 'date_format:Y-m-d', 'after:1999-12-31', 'before:2100-01-01']],
            attributes: ['as_of' => 'hisoblash sanasi'],
        );

        $asOf = CarbonImmutable::parse($validated['as_of'] ?? today());
        $contract->load('owner', 'lines', 'stages', 'deliveries.items.line', 'deliveries.creator', 'penaltyDecisions.decider');

        $penalty = $calculator->forContract($contract, $asOf);
        $decision = $ledger->latestDecision($contract);

        return view('contracts.show', [
            'contract' => $contract,
            'asOf' => $asOf,
            'penalty' => $penalty,
            'decision' => $decision,
            'decisionDiffers' => $decision?->status === PenaltyDecisionStatus::Approved
                && $decision->as_of->equalTo($asOf)
                && ! $decision->calculated->equals($penalty->total),
            'stageStates' => $schedule->for($contract),
        ]);
    }
}
