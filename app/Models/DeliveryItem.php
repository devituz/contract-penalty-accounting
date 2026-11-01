<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryItem extends Model
{
    protected $fillable = ['delivery_id', 'contract_line_id', 'paid_qty', 'bonus_qty'];

    protected function casts(): array
    {
        return ['paid_qty' => 'integer', 'bonus_qty' => 'integer'];
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(ContractLine::class, 'contract_line_id');
    }
}
