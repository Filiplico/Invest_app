<?php

namespace App\Exceptions;

class InsufficientFundsException extends AccountRuleException
{
    public static function make(float $available, float $required): self
    {
        return new self(sprintf(
            'Insufficient funds: the account holds %.2f but this movement requires %.2f.',
            $available,
            $required
        ));
    }
}
