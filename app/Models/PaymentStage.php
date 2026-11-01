<?php

namespace App\Models;

use App\Domain\Money;
use App\Enums\StageCondition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentStage extends Model
{
    protected $fillable = ['contract_id', 'position', 'name', 'percent_bp', 'condition', 'due_date'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'percent_bp' => 'integer',
            'condition' => StageCondition::class,
            'due_date' => 'immutable_date',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function percentLabel(): string
    {
        return self::formatPercent($this->percent_bp).'%';
    }

    public static function formatPercent(int $basisPoints): string
    {
        return rtrim(rtrim(Money::ofMinor($basisPoints)->format(), '0'), ',');
    }
}
