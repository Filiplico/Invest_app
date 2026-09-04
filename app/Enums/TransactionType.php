<?php

namespace App\Enums;

enum TransactionType: string
{
    case Deposit = 'deposit';
    case Withdrawal = 'withdrawal';
    case Buy = 'buy';
    case Sell = 'sell';

    public function isTrade(): bool
    {
        return $this === self::Buy || $this === self::Sell;
    }

    public function addsCash(): bool
    {
        return $this === self::Deposit || $this === self::Sell;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
