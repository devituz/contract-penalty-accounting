<?php

namespace App\Enums;

enum ContractStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Qoralama',
            self::Active => 'Faol',
            self::Completed => 'Yakunlangan',
        };
    }
}
