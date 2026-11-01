<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Domain\Money;
use App\Domain\RuleViolation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractLine extends Model
{
    protected $fillable = ['contract_id', 'name', 'paid_qty', 'bonus_qty', 'price'];

    protected function casts(): array
    {
        return [
            'paid_qty' => 'integer',
            'bonus_qty' => 'integer',
            'price' => MoneyCast::class,
        ];
    }

    protected static function booted(): void
    {
        $guard = function (self $line): void {
            if (! $line->contract->isDraft()) {
                throw new RuleViolation('Shartnoma faollashgan: tovar qatorlarini o\'zgartirib bo\'lmaydi.');
            }
        };

        static::saving($guard);
        static::deleting($guard);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function paidAmount(): Money
    {
        return $this->price->times($this->paid_qty);
    }
}
