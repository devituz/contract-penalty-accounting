@extends('layout')

@section('title', $contract->number)

@section('content')
    <h1>Shartnoma {{ $contract->number }} @include('partials.contract-status', ['status' => $contract->status])</h1>
    <p class="sub">{{ $contract->supplier }} · ijrochi: {{ $contract->owner->name }}</p>

    <div class="actions">
        @can('update', $contract)
            <a class="btn ghost" href="{{ route('contracts.edit', $contract) }}">Tahrirlash</a>
        @endcan
        @if ($contract->isDraft())
            @can('activate', $contract)
                <form method="post" action="{{ route('contracts.activate', $contract) }}" class="inline">
                    @csrf
                    <button>Faollashtirish</button>
                </form>
            @endcan
        @endif
        @can('viewAccounting', App\Models\Contract::class)
            <a class="btn ghost" href="{{ route('accounting.show', $contract) }}">Buxgalteriya sahifasi</a>
        @endcan
    </div>

    <div class="panel">
        <dl class="facts">
            <div><dt>Boshlanish</dt><dd>{{ $contract->start_date->toDateString() }}</dd></div>
            <div><dt>Muddat</dt><dd>{{ $contract->term_days }} kun</dd></div>
            <div><dt>Muddat sanasi</dt><dd>{{ $contract->dueDate()->toDateString() }}</dd></div>
            <div><dt>Pullik summa</dt><dd>{{ $contract->paidTotal()->format() }}</dd></div>
        </dl>
    </div>

    <div class="panel">
        <h2>Qoldiq jadvali</h2>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Tovar</th><th class="num">Narx</th>
                    <th class="num">Pullik</th><th class="num">Kelgan</th><th class="num">Qoldiq</th><th class="num">Qoldiq summa</th>
                    <th class="num">Bonus</th><th class="num">Kelgan</th><th class="num">Qoldiq</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($contract->lineBalances() as $balance)
                    <tr>
                        <td>{{ $balance->line->name }}</td>
                        <td class="num">{{ $balance->line->price->format() }}</td>
                        <td class="num">{{ $balance->line->paid_qty }}</td>
                        <td class="num">{{ $balance->paidDelivered }}</td>
                        <td class="num">{{ $balance->paidRemaining() }}</td>
                        <td class="num">{{ $balance->line->price->times($balance->paidRemaining())->format() }}</td>
                        <td class="num">{{ $balance->line->bonus_qty }}</td>
                        <td class="num">{{ $balance->bonusDelivered }}</td>
                        <td class="num">{{ $balance->bonusRemaining() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="muted">Tovar qatorlari yo'q. Faollashtirish uchun kamida 1 ta qator kerak.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if (! $contract->isDraft())
        @can('deliver', $contract)
            <div class="panel">
                <h2>Partiya kiritish</h2>
                <form method="post" action="{{ route('contracts.deliveries.store', $contract) }}">
                    @csrf
                    <div class="row">
                        <div>
                            <label for="delivered_on">Sana</label>
                            <input id="delivered_on" type="date" name="delivered_on" value="{{ old('delivered_on', today()->toDateString()) }}" required>
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Tovar</th><th>Pullik miqdor</th><th>Bonus miqdor</th></tr></thead>
                            <tbody>
                            @foreach ($contract->lineBalances() as $balance)
                                <tr>
                                    <td>{{ $balance->line->name }} <span class="muted">(qoldiq: {{ $balance->paidRemaining() }} + {{ $balance->bonusRemaining() }} bonus)</span></td>
                                    <td><input type="number" min="0" name="items[{{ $balance->line->id }}][paid_qty]" value="{{ old('items.'.$balance->line->id.'.paid_qty', 0) }}" aria-label="Pullik miqdor"></td>
                                    <td><input type="number" min="0" name="items[{{ $balance->line->id }}][bonus_qty]" value="{{ old('items.'.$balance->line->id.'.bonus_qty', 0) }}" aria-label="Bonus miqdor"></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="actions"><button>Partiyani saqlash</button></div>
                </form>
            </div>
        @endcan
    @endif

    @if ($contract->deliveries->isNotEmpty())
        <div class="panel">
            <h2>Kelgan partiyalar</h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Sana</th><th>Tovar</th><th class="num">Pullik</th><th class="num">Bonus</th><th>Kiritgan</th></tr></thead>
                    <tbody>
                    @foreach ($contract->deliveries as $delivery)
                        @foreach ($delivery->items as $item)
                            <tr>
                                <td>{{ $delivery->delivered_on->toDateString() }}</td>
                                <td>{{ $item->line->name }}</td>
                                <td class="num">{{ $item->paid_qty }}</td>
                                <td class="num">{{ $item->bonus_qty }}</td>
                                <td>{{ $delivery->creator->name }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="panel">
        <h2>Jarima</h2>
        <form method="get" action="{{ route('contracts.show', $contract) }}" class="row">
            <div>
                <label for="as_of">Hisoblash sanasi</label>
                <input id="as_of" type="date" name="as_of" value="{{ $asOf->toDateString() }}">
            </div>
            <button class="ghost">Hisoblash</button>
        </form>

        @if ($contract->isDraft())
            <p class="muted">Shartnoma qoralama: u kuchga kirmagan, jarima hisoblanmaydi.</p>
        @else
            <dl class="facts" style="margin-top: 12px">
                <div><dt>Muddat sanasi</dt><dd>{{ $penalty->dueDate->toDateString() }}</dd></div>
                <div><dt>Kechikish</dt><dd>{{ $penalty->delayDays }} kun</dd></div>
                <div><dt>Jarima</dt><dd>{{ $penalty->total->format() }}</dd></div>
                <div><dt>Limit</dt><dd>{{ $penalty->limit->format() }}</dd></div>
                <div>
                    <dt>Boshliq qarori</dt>
                    <dd>
                        @if ($decision === null)
                            <span class="badge warn">Tasdiqlanmagan</span>
                        @else
                            <span @class(['badge', 'ok' => $decision->status === App\Enums\PenaltyDecisionStatus::Approved, 'bad' => $decision->status === App\Enums\PenaltyDecisionStatus::Rejected])>{{ $decision->status->label() }}</span>
                        @endif
                    </dd>
                </div>
            </dl>

            @if ($penalty->limitReached)
                <p class="alert warn" style="margin-top: 12px">Jarima limitga yetdi: keyingi kunlar jamiga qo'shilmaydi.</p>
            @endif
            @if ($decisionDiffers)
                <p class="alert warn" style="margin-top: 12px">Tasdiqlangan summa joriy hisobdan farq qiladi: qaror {{ $decision->calculated->format() }} asosida qabul qilingan, hozirgi hisob {{ $penalty->total->format() }}. Kerak bo'lsa qayta tasdiqlang.</p>
            @endif

            @if ($penalty->days !== [])
                <h2 style="margin-top: 14px">Kun-kunlik jadval</h2>
                <div class="scroll">
                    <table>
                        <thead><tr><th>Sana</th><th class="num">Kelmagan summa</th><th class="num">O'sha kun jarimasi</th><th class="num">Jami</th></tr></thead>
                        <tbody>
                        @foreach ($penalty->days as $day)
                            <tr>
                                <td>{{ $day->date->toDateString() }}</td>
                                <td class="num">{{ $day->undelivered->format() }}</td>
                                <td class="num">{{ $day->penalty->format() }}</td>
                                <td class="num">{{ $day->runningTotal->format() }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="muted" style="margin-top: 12px">Bu sana bo'yicha kechikish yo'q.</p>
            @endif

            @can('decidePenalty', $contract)
                <h2 style="margin-top: 14px">Boshliq qarori</h2>
                <form method="post" class="row">
                    @csrf
                    <input type="hidden" name="as_of" value="{{ $asOf->toDateString() }}">
                    <div>
                        <label for="amount">Tasdiqlanadigan summa (bo'sh: hisoblangan summa)</label>
                        <input id="amount" inputmode="decimal" name="amount" value="{{ old('amount') }}" placeholder="{{ $penalty->total->plain() }}">
                    </div>
                    <div>
                        <label for="note">Izoh</label>
                        <input id="note" name="note" value="{{ old('note') }}">
                    </div>
                    <button formaction="{{ route('contracts.penalty.approve', $contract) }}">Tasdiqlash</button>
                    <button class="danger" formaction="{{ route('contracts.penalty.reject', $contract) }}">Rad etish</button>
                </form>
            @endcan

            @if ($contract->penaltyDecisions->isNotEmpty())
                <h2 style="margin-top: 14px">Qarorlar tarixi</h2>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Sana</th><th>Qaror</th><th>Hisoblash sanasi</th><th class="num">Hisoblangan</th><th class="num">Tasdiqlangan</th><th>Izoh</th><th>Kim</th></tr></thead>
                        <tbody>
                        @foreach ($contract->penaltyDecisions as $item)
                            <tr>
                                <td>{{ $item->created_at->format('Y-m-d H:i') }}</td>
                                <td>{{ $item->status->label() }}</td>
                                <td>{{ $item->as_of->toDateString() }}</td>
                                <td class="num">{{ $item->calculated->format() }}</td>
                                <td class="num">{{ $item->approved?->format() ?? '—' }}</td>
                                <td>{{ $item->note ?? '—' }}</td>
                                <td>{{ $item->decider->name }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </div>

    <div class="panel">
        <h2>To'lov bosqichlari</h2>
        @include('partials.stages-table', ['states' => $stageStates])
    </div>
@endsection
