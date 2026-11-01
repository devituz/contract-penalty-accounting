<?php

namespace App\Domain;

use RuntimeException;

/** Biznes qoidasi buzildi. Xabar foydalanuvchiga aynan ko'rsatiladi. */
class RuleViolation extends RuntimeException
{
    public function __construct(string $message, public readonly string $field = 'rule')
    {
        parent::__construct($message);
    }
}
