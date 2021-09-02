<?php

namespace Overtrue\LaravelSaml;

class SamlAuthenticationException extends Exception
{
    public function __construct(public string $lastErrorReason, \Exception $previous = null)
    {
        parent::__construct('Unauthenticated', 0, $previous);
    }
}
