@extends('layout')

@php
    $editing = $contract->exists;
    $lineRows = old('lines', $lines);
    $stageRows = old('stages', $stages);
@endphp

@section('title', $editing ? 'Shartnomani tahrirlash' : 'Yangi shartnoma')

@section('content')
    <h1>{{ $editing ? 'Shartnoma: '.$contract->number : 'Yangi shartnoma' }}</h1>
    <p class="sub">Qoralama. Faollashtirilgandan keyin tovar qatorlari o'zgarmaydi.</p>

    <form method="post" action="{{ $editing ? route('contracts.update', $contract) : route('contracts.store') }}">
        @csrf
        @if ($editing) @method('put') @endif

        <div class="panel">
            <div class="row">
                <div><label for="number">Raqam</label><input id="number" name="number" value="{{ old('number', $contract->number) }}" required></div>
                <div><label for="supplier">Yetkazuvchi</label><input id="supplier" name="supplier" value="{{ old('supplier', $contract->supplier) }}" required></div>
                <div><label for="start_date">Boshlanish sanasi</label><input id="start_date" type="date" name="start_date" value="{{ old('start_date', $contract->start_date?->toDateString()) }}" required></div>
                <div><label for="term_days">Muddat (kun)</label><input id="term_days" type="number" min="1" name="term_days" value="{{ old('term_days', $contract->term_days) }}" required></div>
            </div>
        </div>

        <div class="panel">
            <h2>Tovar qatorlari</h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Nomi</th><th>Pullik soni</th><th>Bonus soni</th><th>Narxi</th><th></th></tr></thead>
                    <tbody id="lines">
                    @foreach ($lineRows as $i => $line)
                        <tr>
                            <td><input name="lines[{{ $i }}][name]" value="{{ $line['name'] ?? '' }}" aria-label="Tovar nomi"></td>
                            <td><input type="number" min="0" name="lines[{{ $i }}][paid_qty]" value="{{ $line['paid_qty'] ?? '' }}" aria-label="Pullik soni"></td>
                            <td><input type="number" min="0" name="lines[{{ $i }}][bonus_qty]" value="{{ $line['bonus_qty'] ?? 0 }}" aria-label="Bonus soni"></td>
                            <td><input inputmode="decimal" name="lines[{{ $i }}][price]" value="{{ $line['price'] ?? '' }}" aria-label="Narxi"></td>
                            <td><button type="button" class="ghost" data-remove>Olib tashlash</button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="actions"><button type="button" class="ghost" data-add="lines">Qator qo'shish</button></div>
        </div>

        <div class="panel">
            <h2>To'lov bosqichlari</h2>
            <p class="muted">Foizlar yig'indisi 100% bo'lishi kerak. Tartib: yuqoridan pastga.</p>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Nomi</th><th>Foiz</th><th>To'lash sharti</th><th>Muddat sanasi (ixtiyoriy)</th><th></th></tr></thead>
                    <tbody id="stages">
                    @foreach ($stageRows as $i => $stage)
                        <tr>
                            <td><input name="stages[{{ $i }}][name]" value="{{ $stage['name'] ?? '' }}" aria-label="Bosqich nomi"></td>
                            <td><input inputmode="decimal" name="stages[{{ $i }}][percent]" value="{{ $stage['percent'] ?? '' }}" aria-label="Foiz"></td>
                            <td>
                                <select name="stages[{{ $i }}][condition]" aria-label="To'lash sharti">
                                    @foreach (App\Enums\StageCondition::cases() as $condition)
                                        <option value="{{ $condition->value }}" @selected(($stage['condition'] ?? null) === $condition->value)>{{ $condition->label() }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input type="date" name="stages[{{ $i }}][due_date]" value="{{ $stage['due_date'] ?? '' }}" aria-label="Muddat sanasi"></td>
                            <td><button type="button" class="ghost" data-remove>Olib tashlash</button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="actions"><button type="button" class="ghost" data-add="stages">Bosqich qo'shish</button></div>
        </div>

        <button>Saqlash</button>
    </form>
@endsection

@push('scripts')
<script>
    let next = 1000;

    document.addEventListener('click', (event) => {
        const remove = event.target.closest('[data-remove]');
        if (remove) {
            const row = remove.closest('tr');

            // Oxirgi qator yangi qator uchun andoza, shuning uchun u o'chirilmaydi, tozalanadi.
            if (row.parentElement.children.length === 1) {
                row.querySelectorAll('input').forEach((field) => { field.value = ''; });
            } else {
                row.remove();
            }

            return;
        }

        const add = event.target.closest('[data-add]');
        if (!add) return;

        const body = document.getElementById(add.dataset.add);
        const template = body.querySelector('tr');
        if (!template) return;

        const row = template.cloneNode(true);
        const index = next++;
        row.querySelectorAll('input, select').forEach((field) => {
            field.name = field.name.replace(/\[\d+\]/, `[${index}]`);
            if (field.tagName === 'INPUT') field.value = '';
        });
        body.appendChild(row);
    });
</script>
@endpush
