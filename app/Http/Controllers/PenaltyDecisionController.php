<?php

namespace App\Http\Controllers;

use App\Domain\Money;
use App\Domain\Penalty\PenaltyService;
use App\Http\Requests\PenaltyDecisionRequest;
use App\Models\Contract;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;

class PenaltyDecisionController extends Controller
{
    public function approve(PenaltyDecisionRequest $request, Contract $contract, PenaltyService $service): RedirectResponse
    {
        $amount = $request->validated('amount');

        $service->approve(
            $contract,
            CarbonImmutable::parse($request->validated('as_of')),
            $amount === null ? null : Money::parse($amount),
            $request->validated('note'),
            $request->user(),
        );

        return back()->with('status', 'Jarima tasdiqlandi.');
    }

    public function reject(PenaltyDecisionRequest $request, Contract $contract, PenaltyService $service): RedirectResponse
    {
        $service->reject(
            $contract,
            CarbonImmutable::parse($request->validated('as_of')),
            $request->validated('note'),
            $request->user(),
        );

        return back()->with('status', 'Jarima rad etildi.');
    }
}
