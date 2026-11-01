<?php

namespace App\Enums;

enum StageCondition: string
{
    case AfterActivation = 'after_activation';
    case AfterFullDelivery = 'after_full_delivery';

    public function label(): string
    {
        return match ($this) {
            self::AfterActivation => 'Shartnoma faollashgandan keyin',
            self::AfterFullDelivery => 'Pullik tovar to\'liq kelgandan keyin',
        };
    }
}
