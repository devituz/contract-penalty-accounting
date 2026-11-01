<span @class([
    'badge',
    'ok' => $status === App\Enums\StageStatus::Paid,
    'info' => $status === App\Enums\StageStatus::Ready,
    'warn' => $status === App\Enums\StageStatus::PartiallyPaid,
    'bad' => $status === App\Enums\StageStatus::Overdue,
])>{{ $status->label() }}</span>
