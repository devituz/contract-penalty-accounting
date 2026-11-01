<?php

namespace App\Http\Controllers;

use App\Domain\Contract\DeliveryService;
use App\Http\Requests\DeliveryRequest;
use App\Models\Contract;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;

class DeliveryController extends Controller
{
    public function store(DeliveryRequest $request, Contract $contract, DeliveryService $service): RedirectResponse
    {
        $service->record(
            $contract,
            CarbonImmutable::parse($request->validated('delivered_on')),
            $request->validated('items'),
            $request->user(),
        );

        return redirect()->route('contracts.show', $contract)->with('status', 'Partiya kiritildi.');
    }
}
