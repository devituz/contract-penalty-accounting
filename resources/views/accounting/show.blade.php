@extends('layout')

@section('title', 'Buxgalteriya: '.$contract->number)

@section('content')
    <h1>Buxgalteriya: {{ $contract->number }} @include('partials.contract-status', ['status' => $contract->status])</h1>
    <p class="sub">{{ $contract->supplier }} · <a href="{{ route('contracts.show', $contract) }}">shartnoma sahifasi</a></p>

    <div class="panel">
        <dl class="facts">
            <div><dt>Pullik summa</dt><dd>{{ $total->format() }}</dd></div>
            <div><dt>To'langan</dt><dd>{{ $paid->format() }}</dd></div>
            <div><dt>Qoldiq</dt><dd>{{ $remaining->format() }}</dd></div>
            <div><dt>Tasdiqlangan jarima</dt><dd>{{ $penalty->format() }}</dd></div>
        </dl>
        @if ($penalty->isPositive())
            <p class="muted" style="margin: 10px 0 0">Jarima yetkazuvchidan alohida undiriladi: u bosqich summasini, reestrni va smetani o'zgartirmaydi.</p>
        @endif
    </div>

    @if ($stageGap)
        <div class="alert warn">«{{ $stageGap->stage->name }}» bosqichida {{ $stageGap->remaining->format() }} qoldiq bor, keyingi bosqichda esa faol to'lov turibdi. Bosqichni qayta to'lang.</div>
    @endif

    <div class="panel">
        <h2>To'lov bosqichlari</h2>
        @include('partials.stages-table', ['states' => $states])
    </div>

    @can('pay', App\Models\Contract::class)
        <div class="panel">
            <h2>To'lov kiritish</h2>
            <form method="post" action="{{ route('accounting.payments.store', $contract) }}" class="row">
                @csrf
                <div>
                    <label for="stage_id">Bosqich</label>
                    <select id="stage_id" name="stage_id" required>
                        @foreach ($states as $state)
                            <option value="{{ $state->stage->id }}" @selected((int) old('stage_id') === $state->stage->id)>{{ $state->stage->name }} (qoldiq {{ $state->remaining->format() }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="amount">Summa</label>
                    <input id="amount" inputmode="decimal" name="amount" value="{{ old('amount') }}" required>
                </div>
                <div>
                    <label for="paid_on">Sana</label>
                    <input id="paid_on" type="date" name="paid_on" value="{{ old('paid_on', today()->toDateString()) }}" required>
                </div>
                <div>
                    <label for="budget_line_id">Smeta qatori</label>
                    <select id="budget_line_id" name="budget_line_id" required>
                        @foreach ($budgetLines as $line)
                            <option value="{{ $line->id }}" @selected((int) old('budget_line_id') === $line->id)>{{ $line->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="reference">To'lov raqami / izoh</label>
                    <input id="reference" name="reference" value="{{ old('reference') }}">
                </div>
                <button>To'lovni saqlash</button>
            </form>
        </div>
    @endcan

    <div class="panel">
        <h2>Faol to'lovlar</h2>
        @include('partials.payments-table', ['payments' => $activePayments, 'voided' => false, 'canVoid' => auth()->user()->can('void', App\Models\Payment::class)])
    </div>

    <div class="panel">
        <h2>Bekor qilingan to'lovlar</h2>
        @include('partials.payments-table', ['payments' => $voidedPayments, 'voided' => true])
    </div>
@endsection
