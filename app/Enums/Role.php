<?php

namespace App\Enums;

enum Role: string
{
    case Executor = 'executor';
    case Boss = 'boss';
    case Accountant = 'accountant';

    public function label(): string
    {
        return match ($this) {
            self::Executor => 'Ijrochi',
            self::Boss => 'Boshliq',
            self::Accountant => 'Buxgalter',
        };
    }
}
