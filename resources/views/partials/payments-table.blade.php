<div class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>#</th>
            @if ($showContract ?? false)<th>Shartnoma</th>@endif
            <th>Sana</th><th class="num">Summa</th><th>Bosqich</th><th>Smeta oyi</th><th>Raqam / izoh</th><th>Kiritgan</th>
            @if ($voided)
                <th>Bekor qilingan</th><th>Sabab</th>
            @elseif ($canVoid ?? false)
                <th>Bekor qilish</th>
            @endif
        </tr>
        </thead>
        <tbody>
        @forelse ($payments as $payment)
            <tr>
                <td>{{ $payment->id }}</td>
                @if ($showContract ?? false)
                    <td><a href="{{ route('accounting.show', $payment->stage->contract_id) }}">{{ $payment->stage->contract->number }}</a></td>
                @endif
                <td>{{ $payment->paid_on->toDateString() }}</td>
                <td class="num">{{ $payment->amount->format() }}</td>
                <td>{{ $payment->stage->name }}</td>
                <td>{{ $payment->budgetLine->label() }}</td>
                <td>{{ $payment->reference ?? '—' }}</td>
                <td>{{ $payment->creator->name }}</td>
                @if ($voided)
                    <td>{{ $payment->voided_at->format('Y-m-d H:i') }}<br><span class="muted">{{ $payment->voider->name }}</span></td>
                    <td>{{ $payment->void_reason }}</td>
                @elseif ($canVoid ?? false)
                    <td>
                        <form method="post" action="{{ route('accounting.payments.void', $payment) }}" class="row">
                            @csrf
                            <input name="reason" placeholder="Sabab" required maxlength="1000" aria-label="Bekor qilish sababi">
                            <button class="danger">Bekor qilish</button>
                        </form>
                    </td>
                @endif
            </tr>
        @empty
            <tr><td colspan="10" class="muted">{{ $voided ? 'Bekor qilingan to\'lov yo\'q.' : 'Faol to\'lov yo\'q.' }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
