<?php

namespace Overtrue\LaravelSaml;

class SamlAssertException extends Exception
{
    public function __construct(public array $errors, public string $lastErrorReason, \Exception $previous = null)
    {
        $message = 'SAML Assertion failed: ' . $lastErrorReason;

        parent::__construct($message, 0, $previous);
    }
}
