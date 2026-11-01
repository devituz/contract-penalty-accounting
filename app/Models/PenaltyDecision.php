<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Domain\Money;
use App\Enums\PenaltyDecisionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenaltyDecision extends Model
{
    protected $fillable = ['contract_id', 'status', 'as_of', 'calculated', 'approved', 'note', 'decided_by'];

    protected function casts(): array
    {
        return [
            'status' => PenaltyDecisionStatus::class,
            'as_of' => 'immutable_date',
            'calculated' => MoneyCast::class,
            'approved' => MoneyCast::class,
        ];
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** Boshliq tasdiqlagan summa, u bo'lmasa hisoblangan summa. */
    public function effectiveAmount(): Money
    {
        return $this->approved ?? $this->calculated;
    }
}
