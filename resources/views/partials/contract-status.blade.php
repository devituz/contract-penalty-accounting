<span @class([
    'badge',
    'info' => $status === App\Enums\ContractStatus::Active,
    'ok' => $status === App\Enums\ContractStatus::Completed,
])>{{ $status->label() }}</span>
