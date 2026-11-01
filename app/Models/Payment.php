<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Domain\RuleViolation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Reestr yozuvi. O'chirilmaydi, faqat bir marta bekor qilinadi. */
class Payment extends Model
{
    private const VOID_FIELDS = ['voided_at', 'voided_by', 'void_reason', 'updated_at'];

    protected $fillable = [
        'payment_stage_id', 'budget_line_id', 'amount', 'paid_on', 'reference',
        'created_by', 'voided_at', 'voided_by', 'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class,
            'paid_on' => 'immutable_date',
            'voided_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (): void {
            throw new RuleViolation('To\'lov o\'chirilmaydi: uni sabab ko\'rsatib bekor qiling.');
        });

        static::updating(function (self $payment): void {
            $onlyVoiding = array_diff(array_keys($payment->getDirty()), self::VOID_FIELDS) === [];

            if ($payment->getOriginal('voided_at') !== null || ! $onlyVoiding) {
                throw new RuleViolation('Reestr yozuvi o\'zgartirilmaydi.');
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }

    public function scopeVoided(Builder $query): Builder
    {
        return $query->whereNotNull('voided_at');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(PaymentStage::class, 'payment_stage_id');
    }

    public function budgetLine(): BelongsTo
    {
        return $this->belongsTo(BudgetLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
