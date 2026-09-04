<?php

namespace App\Exceptions;

class InsufficientHoldingsException extends AccountRuleException
{
    public static function make(string $instrument, int $owned, int $requested): self
    {
        return new self(sprintf(
            'Insufficient holdings: the client owns %d unit(s) of %s but tried to sell %d.',
            $owned,
            $instrument,
            $requested
        ));
    }
}
