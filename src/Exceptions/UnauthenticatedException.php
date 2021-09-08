<?php

namespace Overtrue\LaravelSaml\Exceptions;

use JetBrains\PhpStorm\Pure;

class UnauthenticatedException extends Exception
{
    #[Pure]
    public function __construct(public ?string $lastErrorReason, \Exception $previous = null)
    {
        parent::__construct($lastErrorReason ?? 'Unauthenticated', 0, $previous);
    }
}
