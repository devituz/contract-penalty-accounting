<?php

namespace App\Http\Controllers;

use App\Domain\Accounting\BudgetLedger;
use App\Domain\Accounting\BudgetService;
use App\Domain\Money;
use App\Http\Requests\BudgetLineRequest;
use App\Models\BudgetLine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BudgetController extends Controller
{
    public function index(BudgetLedger $ledger): View
    {
        Gate::authorize('viewAny', BudgetLine::class);

        $spent = $ledger->spentByLine();

        return view('budget.index', [
            'rows' => BudgetLine::orderByDesc('fiscal_year')->orderBy('expense_item')->orderBy('month')->get()
                ->map(function (BudgetLine $line) use ($spent) {
                    $paid = Money::ofMinor((int) ($spent[$line->id] ?? 0));

                    return ['line' => $line, 'spent' => $paid, 'remaining' => $line->allocated->minus($paid)];
                }),
        ]);
    }

    public function store(BudgetLineRequest $request, BudgetService $service): RedirectResponse
    {
        $data = $request->validated();

        $service->create((int) $data['fiscal_year'], $data['expense_item'], (int) $data['month'], Money::parse($data['allocated']));

        return redirect()->route('budget.index')->with('status', 'Smeta qatori qo\'shildi.');
    }

    public function update(BudgetLineRequest $request, BudgetLine $budgetLine, BudgetService $service): RedirectResponse
    {
        $service->updateAllocation($budgetLine, Money::parse($request->validated('allocated')));

        return redirect()->route('budget.index')->with('status', 'Ajratilgan summa yangilandi.');
    }
}
