<?php

namespace App\Exceptions;

use Exception;

abstract class AccountRuleException extends Exception
{
    abstract public function errorCode(): string;
}
