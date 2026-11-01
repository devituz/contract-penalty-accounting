<div class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>Bosqich</th><th class="num">Foiz</th><th class="num">Summa</th><th class="num">To'langan</th>
            <th class="num">Qoldiq</th><th>Qachon to'lanadi</th><th>Muddat</th><th>Holat</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($states as $state)
            <tr>
                <td>{{ $state->stage->name }}</td>
                <td class="num">{{ $state->stage->percentLabel() }}</td>
                <td class="num">{{ $state->amount->format() }}</td>
                <td class="num">{{ $state->paid->format() }}</td>
                <td class="num">{{ $state->remaining->format() }}</td>
                <td>{{ $state->stage->condition->label() }}</td>
                <td>{{ $state->stage->due_date?->toDateString() ?? '—' }}</td>
                <td>@include('partials.stage-status', ['status' => $state->status])</td>
            </tr>
        @empty
            <tr><td colspan="8" class="muted">Bosqichlar kiritilmagan.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
