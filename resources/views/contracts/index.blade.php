@extends('layout')

@section('title', 'Shartnomalar')

@section('content')
    <h1>Shartnomalar</h1>
    <div class="actions">
        @can('create', App\Models\Contract::class)
            <a class="btn" href="{{ route('contracts.create') }}">Yangi shartnoma</a>
        @endcan
    </div>
    <div class="panel table-wrap">
        <table>
            <thead>
            <tr><th>Raqam</th><th>Yetkazuvchi</th><th>Boshlanish</th><th>Muddat sanasi</th><th class="num">Pullik summa</th><th>Holat</th></tr>
            </thead>
            <tbody>
            @forelse ($contracts as $contract)
                <tr>
                    <td><a href="{{ route('contracts.show', $contract) }}">{{ $contract->number }}</a></td>
                    <td>{{ $contract->supplier }}</td>
                    <td>{{ $contract->start_date->toDateString() }}</td>
                    <td>{{ $contract->dueDate()->toDateString() }}</td>
                    <td class="num">{{ $contract->paidTotal()->format() }}</td>
                    <td>@include('partials.contract-status', ['status' => $contract->status])</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">Shartnoma yo'q.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
