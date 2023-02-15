<?php

namespace Overtrue\LaravelSaml\Exceptions;

use JetBrains\PhpStorm\Pure;

class AssertException extends Exception
{
    #[Pure]
    public function __construct(public array $errors, public ?string $lastErrorReason = null, \Exception $previous = null)
    {
        $message = 'SAML Assertion failed: '.($lastErrorReason ?? 'Known');

        parent::__construct($message, 0, $previous);
    }
}
