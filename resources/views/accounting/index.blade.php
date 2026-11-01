@extends('layout')

@section('title', 'Buxgalteriya')

@section('content')
    <h1>Buxgalteriya</h1>
    <p class="sub">Barcha raqamlar to'lov reestridan hisoblanadi.</p>
    <div class="panel table-wrap">
        <table>
            <thead>
            <tr><th>Shartnoma</th><th>Yetkazuvchi</th><th>Holat</th><th class="num">Pullik summa</th><th class="num">To'langan</th><th class="num">Qoldiq</th><th class="num">Tasdiqlangan jarima</th></tr>
            </thead>
            <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td><a href="{{ route('accounting.show', $row['contract']) }}">{{ $row['contract']->number }}</a></td>
                    <td>{{ $row['contract']->supplier }}</td>
                    <td>@include('partials.contract-status', ['status' => $row['contract']->status])</td>
                    <td class="num">{{ $row['total']->format() }}</td>
                    <td class="num">{{ $row['paid']->format() }}</td>
                    <td class="num">{{ $row['remaining']->format() }}</td>
                    <td class="num">{{ $row['penalty']->format() }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">Shartnoma yo'q.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
