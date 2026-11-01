<?php

namespace App\Enums;

enum PenaltyDecisionStatus: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'Tasdiqlangan',
            self::Rejected => 'Rad etilgan',
        };
    }
}
