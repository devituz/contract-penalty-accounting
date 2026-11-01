@extends('layout')

@section('title', 'Smeta')

@section('content')
    <h1>Smeta</h1>
    <p class="sub">Qoldiq = ajratilgan − shu qatordan faol to'lovlar. Bekor qilingan to'lov hisobga kirmaydi.</p>

    <div class="panel table-wrap">
        <table>
            <thead>
            <tr>
                <th>Yil</th><th>Xarajat moddasi</th><th>Oy</th><th class="num">Ajratilgan</th><th class="num">To'langan</th><th class="num">Qoldiq</th>
                @can('manage', App\Models\BudgetLine::class)<th>Summani o'zgartirish</th>@endcan
            </tr>
            </thead>
            <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['line']->fiscal_year }}</td>
                    <td>{{ $row['line']->expense_item }}</td>
                    <td>{{ $row['line']->monthName() }}</td>
                    <td class="num">{{ $row['line']->allocated->format() }}</td>
                    <td class="num">{{ $row['spent']->format() }}</td>
                    <td class="num">{{ $row['remaining']->format() }}</td>
                    @can('manage', App\Models\BudgetLine::class)
                        <td>
                            <form method="post" action="{{ route('budget.update', $row['line']) }}" class="row">
                                @csrf
                                @method('put')
                                <input inputmode="decimal" name="allocated" value="{{ $row['line']->allocated->plain() }}" aria-label="Ajratilgan summa">
                                <button class="ghost">Saqlash</button>
                            </form>
                        </td>
                    @endcan
                </tr>
            @empty
                <tr><td colspan="7" class="muted">Smeta qatori yo'q.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @can('manage', App\Models\BudgetLine::class)
        <div class="panel">
            <h2>Yangi smeta qatori</h2>
            <form method="post" action="{{ route('budget.store') }}" class="row">
                @csrf
                <div><label for="fiscal_year">Moliyaviy yil</label><input id="fiscal_year" type="number" name="fiscal_year" value="{{ old('fiscal_year', today()->year) }}" required></div>
                <div><label for="expense_item">Xarajat moddasi</label><input id="expense_item" name="expense_item" value="{{ old('expense_item') }}" required></div>
                <div>
                    <label for="month">Oy</label>
                    <select id="month" name="month" required>
                        @foreach (App\Models\BudgetLine::MONTHS as $number => $name)
                            <option value="{{ $number }}" @selected((int) old('month') === $number)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label for="allocated">Ajratilgan summa</label><input id="allocated" inputmode="decimal" name="allocated" value="{{ old('allocated') }}" required></div>
                <button>Qo'shish</button>
            </form>
        </div>
    @endcan
@endsection
