<?php

namespace App\Models;

use App\Domain\Contract\LineBalance;
use App\Domain\Money;
use App\Enums\ContractStatus;
use App\Enums\Role;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;

class Contract extends Model
{
    protected $fillable = ['number', 'supplier', 'start_date', 'term_days', 'status', 'owner_id', 'activated_at', 'completed_at'];

    protected function casts(): array
    {
        return [
            'start_date' => 'immutable_date',
            'term_days' => 'integer',
            'status' => ContractStatus::class,
            'activated_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ContractLine::class)->orderBy('id');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(PaymentStage::class)->orderBy('position');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class)->orderBy('delivered_on')->orderBy('id');
    }

    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(Payment::class, PaymentStage::class);
    }

    public function penaltyDecisions(): HasMany
    {
        return $this->hasMany(PenaltyDecision::class)->orderBy('id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->hasRole(Role::Executor) ? $query->where('owner_id', $user->id) : $query;
    }

    public function dueDate(): CarbonImmutable
    {
        return $this->start_date->addDays($this->term_days);
    }

    public function isDraft(): bool
    {
        return $this->status === ContractStatus::Draft;
    }

    public function isPayable(): bool
    {
        return in_array($this->status, [ContractStatus::Active, ContractStatus::Completed], true);
    }

    public function paidTotal(): Money
    {
        return $this->lines->reduce(fn (Money $sum, ContractLine $line) => $sum->plus($line->paidAmount()), Money::zero());
    }

    /** @return Collection<int, LineBalance> */
    public function lineBalances(): Collection
    {
        $items = $this->deliveries->flatMap->items->groupBy('contract_line_id');

        return $this->lines->map(fn (ContractLine $line) => new LineBalance(
            $line,
            (int) $items->get($line->id, collect())->sum('paid_qty'),
            (int) $items->get($line->id, collect())->sum('bonus_qty'),
        ));
    }

    public function isFullyDelivered(): bool
    {
        return $this->lines->isNotEmpty()
            && $this->lineBalances()->every(fn (LineBalance $balance) => $balance->paidRemaining() === 0);
    }

    /** @return list<array{date: CarbonImmutable, value: Money}> kelgan pullik tovar qiymati, bonus kirmaydi */
    public function paidReceipts(): array
    {
        $prices = $this->lines->keyBy('id');
        $receipts = [];

        foreach ($this->deliveries as $delivery) {
            foreach ($delivery->items as $item) {
                $receipts[] = [
                    'date' => $delivery->delivered_on,
                    'value' => $prices[$item->contract_line_id]->price->times($item->paid_qty),
                ];
            }
        }

        return $receipts;
    }
}
